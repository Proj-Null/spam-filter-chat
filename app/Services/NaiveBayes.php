<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException; // Added for better error handling
use RuntimeException; // Added for file/training issues

class TrainingMessage
{
    public string $text;
    public bool $isSpam;

    public function __construct(string $text, bool $isSpam)
    {
        $this->text = $text;
        $this->isSpam = $isSpam;
    }
}

class NaiveBayes
{
    // Smoothing factor
    private int $k;

    // Counts during training
    private int $numSpamMessages = 0;
    private int $numHamMessages = 0;
    private array $wordCountsSpam = []; // Stores count of each word in spam messages
    private array $wordCountsHam = [];  // Stores count of each word in ham messages
    private int $totalWordsInSpam = 0; // Total number of words (tokens) in all spam messages
    private int $totalWordsInHam = 0;  // Total number of words (tokens) in all ham messages
    private array $vocabulary = [];    // All unique words encountered during training

    private string $storagePath;
    private bool $isTrained = false; // Flag to indicate if the model is trained

    public function __construct(int $k = 1)
    {
        if ($k <= 0) {
            // Ensure k is positive for Laplace smoothing
             throw new InvalidArgumentException("Smoothing factor k must be positive.");
        }
        $this->k = $k;
        // Use Laravel's storage_path helper correctly
        $this->storagePath = storage_path('app/naive_bayes/classifier.json');

        if ($this->loadTrainedClassifier()) {
             Log::info('NaiveBayes: Trained classifier loaded successfully from ' . $this->storagePath);
             $this->isTrained = true;
        } else {
             Log::warning('NaiveBayes: Trained classifier not found at ' . $this->storagePath . '. Attempting auto-training with dataset...');
             $this->attemptAutoTraining();
        }
    }

    private function attemptAutoTraining(): void
    {
        $dataPath = storage_path('app/dataset'); // Default dataset path

        if (!is_dir($dataPath)) {
            Log::error("NaiveBayes: Dataset directory not found at $dataPath. Cannot auto-train.");
            return; // Cannot train if base directory doesn't exist
        }
        if (!is_dir($dataPath . '/spam') || !is_dir($dataPath . '/ham')) {
             Log::error("NaiveBayes: Spam or Ham subdirectories missing in $dataPath. Cannot auto-train.");
             return; // Cannot train if subdirectories are missing
        }

        $messages = $this->loadMessages($dataPath);
        if (empty($messages)) {
             Log::error('NaiveBayes: No messages loaded from dataset. Auto-training failed.');
             return;
        }

        $this->train($messages); // Train on the entire loaded dataset
         if ($this->isTrained) {
             Log::info('NaiveBayes: Classifier auto-trained and saved successfully.');
         } else {
             Log::error('NaiveBayes: Auto-training completed, but the classifier is still not marked as trained. Check training logic.');
         }
    }


    /**
     * Tokenizes text into individual words (lowercase, alphanumeric).
     * Returns an array containing ALL words (not unique).
     */
    public function tokenize(string $text): array
    {
        // Simpler regex: matches sequences of letters and numbers
        // Convert to lowercase for case-insensitive matching
        preg_match_all("/[a-z0-9]+/", strtolower($text), $matches);
        // Return only non-empty matches
        return array_filter($matches[0] ?? [], fn($word) => strlen($word) >= 1); // Allow single character tokens like 'a' or '1'
    }

    /**
     * Loads messages from specified spam and ham directories.
     */
    public function loadMessages(string $dataPath): array
    {
        $messages = [];
        $spamPath = rtrim($dataPath, '/') . '/spam';
        $hamPath = rtrim($dataPath, '/') . '/ham';

        if (!is_dir($spamPath)) {
            Log::error("NaiveBayes: Spam directory missing: $spamPath");
        } else {
            $spamFiles = glob($spamPath . '/*.txt');
            Log::info("NaiveBayes: Found " . count($spamFiles) . " potential spam files in $spamPath");
            foreach ($spamFiles as $file) {
                $content = file_get_contents($file);
                if ($content === false) {
                     Log::warning("NaiveBayes: Failed to read spam file: $file");
                     continue;
                }
                // Use the specific subject extraction logic if needed, or just use content
                // Assuming the PrepareDataset command adds "Subject: "
                $subject = $this->extractSubject($content, $file);
                if (!empty($subject)) {
                    // Log::debug("NaiveBayes: Loaded spam message from $file: $subject");
                    $messages[] = new TrainingMessage($subject, true);
                } else {
                     Log::warning("NaiveBayes: Empty content/subject extracted from spam file: $file");
                }
            }
        }


        if (!is_dir($hamPath)) {
             Log::error("NaiveBayes: Ham directory missing: $hamPath");
        } else {
             $hamFiles = glob($hamPath . '/*.txt');
             Log::info("NaiveBayes: Found " . count($hamFiles) . " potential ham files in $hamPath");
             foreach ($hamFiles as $file) {
                 $content = file_get_contents($file);
                 if ($content === false) {
                     Log::warning("NaiveBayes: Failed to read ham file: $file");
                     continue;
                 }
                $subject = $this->extractSubject($content, $file);
                 if (!empty($subject)) {
                     // Log::debug("NaiveBayes: Loaded ham message from $file: $subject");
                     $messages[] = new TrainingMessage($subject, false);
                 } else {
                     Log::warning("NaiveBayes: Empty content/subject extracted from ham file: $file");
                 }
             }
        }


        Log::info("NaiveBayes: Loaded " . count($messages) . " total messages for training/evaluation.");
        shuffle($messages); // Shuffle for train/test split randomness
        return $messages;
    }

    /**
      * Extracts Subject line, falling back to full content.
      */
    private function extractSubject(string $content, string $filePath): string
    {
        $lines = explode("\n", $content, 2); // Limit exploding for efficiency
        if (isset($lines[0]) && str_starts_with(strtolower($lines[0]), 'subject:')) {
             $subject = trim(substr($lines[0], strlen('Subject:')));
             // If subject is empty after trimming, maybe log it?
             if(empty($subject)) {
                 Log::warning("NaiveBayes: Found 'Subject:' line but content is empty in file: $filePath. Using full content.");
                 // Fallback to trimmed full content if subject line is empty
                 return trim($content);
             }
             return $subject;
        }
        // Fallback to full content if no Subject: line
         $trimmedContent = trim($content);
         if(empty($trimmedContent)) {
             Log::warning("NaiveBayes: No 'Subject:' line and content is empty in file: $filePath");
         } else {
             // Log::warning("NaiveBayes: No 'Subject:' line found in file: $filePath. Using full trimmed content.");
         }
        return $trimmedContent;
    }

    /**
     * Splits messages into training and testing sets.
     */
    public function trainTestSplit(array $messages, float $trainPct = 0.8): array
    {
        if ($trainPct <= 0 || $trainPct >= 1) {
            throw new InvalidArgumentException("Training percentage must be between 0 and 1.");
        }
        shuffle($messages);
        $numMessages = count($messages);
        if ($numMessages === 0) {
            return [[], []]; // Return empty sets if no messages
        }
        $numTrain = (int) floor($numMessages * $trainPct);
        if ($numTrain == 0 || $numTrain == $numMessages) {
             Log::warning("NaiveBayes: trainTestSplit resulted in an empty train or test set. Adjust percentage or dataset size.");
             // Avoid empty sets if possible, put at least one in each if messages > 1
             if ($numMessages > 1) {
                  $numTrain = max(1, min($numMessages - 1, $numTrain));
             } else {
                  // If only 1 message, put it in training set
                  $numTrain = $numMessages;
             }

        }
        return [
            array_slice($messages, 0, $numTrain), // Training set
            array_slice($messages, $numTrain)     // Testing set
        ];
    }

    /**
     * Trains the Naive Bayes classifier on the provided messages.
     * This completely replaces any previously trained data.
     */
    public function train(array $trainingMessages): void
    {
         // Reset internal state before training
         $this->numSpamMessages = 0;
         $this->numHamMessages = 0;
         $this->wordCountsSpam = [];
         $this->wordCountsHam = [];
         $this->totalWordsInSpam = 0;
         $this->totalWordsInHam = 0;
         $this->vocabulary = [];
         $this->isTrained = false; // Mark as not trained until successful completion
         $vocabularySet = []; // Use a set for efficient unique word tracking


         if (empty($trainingMessages)) {
              Log::error("NaiveBayes: Training failed - received empty list of messages.");
              return; // Cannot train on empty data
         }

         Log::info("NaiveBayes: Starting training with " . count($trainingMessages) . " messages.");

         foreach ($trainingMessages as $msg) {
             if (!$msg instanceof TrainingMessage) continue; // Skip invalid entries

             // Tokenize returns ALL words in the message now
             $tokens = $this->tokenize($msg->text);

             if ($msg->isSpam) {
                 $this->numSpamMessages++;
                 foreach ($tokens as $token) {
                     $this->wordCountsSpam[$token] = ($this->wordCountsSpam[$token] ?? 0) + 1;
                     $this->totalWordsInSpam++;
                     $vocabularySet[$token] = true; // Add to vocabulary set
                 }
             } else {
                 $this->numHamMessages++;
                 foreach ($tokens as $token) {
                     $this->wordCountsHam[$token] = ($this->wordCountsHam[$token] ?? 0) + 1;
                     $this->totalWordsInHam++;
                     $vocabularySet[$token] = true; // Add to vocabulary set
                 }
             }
         }

         // Final vocabulary is the list of unique words
         $this->vocabulary = array_keys($vocabularySet);

         if ($this->numSpamMessages === 0 && $this->numHamMessages === 0) {
              Log::error("NaiveBayes: Training failed - no valid spam or ham messages found in the training data.");
              return; // Training didn't actually happen
         }
         if ($this->numSpamMessages === 0) {
              Log::warning("NaiveBayes: Training completed, but no spam messages were found in the training set.");
         }
         if ($this->numHamMessages === 0) {
               Log::warning("NaiveBayes: Training completed, but no ham messages were found in the training set.");
         }
         if (empty($this->vocabulary)) {
               Log::warning("NaiveBayes: Training completed, but the vocabulary is empty. Check tokenization and input data.");
         }


         Log::info("NaiveBayes: Training complete. Ham Messages: {$this->numHamMessages}, Spam Messages: {$this->numSpamMessages}, Vocabulary Size: " . count($this->vocabulary));
         $this->isTrained = true; // Mark as trained
         $this->saveTrainedClassifier(); // Save the newly trained state
    }

    /**
     * Calculates the probability of a word given it's spam (with smoothing).
     * P(word|Spam)
     */
    private function pWordSpam(string $word): float
    {
        $vocabSize = count($this->vocabulary);
        // Smoothed probability: (count(word in spam) + k) / (total words in spam + k * vocabulary size)
        $numerator = ($this->wordCountsSpam[$word] ?? 0) + $this->k;
        $denominator = $this->totalWordsInSpam + ($this->k * $vocabSize);

        if ($denominator == 0) {
            // This should ideally not happen if vocabSize > 0 or totalWordsInSpam > 0 and k > 0
            Log::error("NaiveBayes: Zero denominator calculating P(word|Spam) for '$word'. VocabSize: $vocabSize, TotalSpamWords: {$this->totalWordsInSpam}, k: {$this->k}");
             // Return a neutral probability or a very small number instead of erroring/returning 0
             // Avoid returning 0 directly as log(0) is undefined.
             return 1.0 / ($vocabSize + 1); // Example small probability
        }

        return $numerator / $denominator;
    }

    /**
     * Calculates the probability of a word given it's ham (with smoothing).
     * P(word|Ham)
     */
    private function pWordHam(string $word): float
    {
        $vocabSize = count($this->vocabulary);
        // Smoothed probability: (count(word in ham) + k) / (total words in ham + k * vocabulary size)
        $numerator = ($this->wordCountsHam[$word] ?? 0) + $this->k;
        $denominator = $this->totalWordsInHam + ($this->k * $vocabSize);

        if ($denominator == 0) {
            Log::error("NaiveBayes: Zero denominator calculating P(word|Ham) for '$word'. VocabSize: $vocabSize, TotalHamWords: {$this->totalWordsInHam}, k: {$this->k}");
             return 1.0 / ($vocabSize + 1); // Example small probability
        }

        return $numerator / $denominator;
    }

    /**
     * Predicts the probability that a given text is spam.
     * Uses Multinomial Naive Bayes approach with log probabilities to avoid underflow.
     */
    public function predict(string $text): float
    {
        if (!$this->isTrained) {
             Log::warning("NaiveBayes: predict() called before the classifier is trained. Returning neutral probability (0.5).");
             return 0.5;
        }

        $totalMessages = $this->numSpamMessages + $this->numHamMessages;
        if ($totalMessages === 0) {
             Log::error("NaiveBayes: Cannot predict - no messages were used in training.");
             return 0.5; // No basis for prediction
        }

        // Calculate log prior probabilities (handle zero counts)
        // Use a small floor for log probabilities to avoid log(0) -> -Infinity
        $logPriorSpam = $this->numSpamMessages > 0 ? log($this->numSpamMessages / $totalMessages) : -1000.0; // Large negative number for log(small prob)
        $logPriorHam = $this->numHamMessages > 0 ? log($this->numHamMessages / $totalMessages) : -1000.0;

        $logLikelihoodSpam = 0.0;
        $logLikelihoodHam = 0.0;

        $tokens = $this->tokenize($text);
        $vocabSet = array_flip($this->vocabulary); // Faster lookups

        // Log::debug('NaiveBayes: Predicting for text: "' . $text . '". Tokens: ' . implode(', ', $tokens));

        foreach ($tokens as $token) {
            // Only consider words that are in the vocabulary learned during training
            if (isset($vocabSet[$token])) {
                $logLikelihoodSpam += log($this->pWordSpam($token));
                $logLikelihoodHam += log($this->pWordHam($token));
                // Optional: Log word probabilities for debugging
                // Log::debug(" Word: $token, logP(W|S): " . log($this->pWordSpam($token)) . ", logP(W|H): " . log($this->pWordHam($token)));
            }
            // Note: Words not in the vocabulary are ignored in standard Multinomial NB calculation
            // The probability calculation relies only on words present in the message AND vocabulary.
        }

        // Calculate posterior log probabilities (proportional)
        $logPosteriorSpam = $logPriorSpam + $logLikelihoodSpam;
        $logPosteriorHam = $logPriorHam + $logLikelihoodHam;

         // Log::debug("Log Priors: Spam=$logPriorSpam, Ham=$logPriorHam");
         // Log::debug("Log Likelihoods: Spam=$logLikelihoodSpam, Ham=$logLikelihoodHam");
         // Log::debug("Log Posteriors: Spam=$logPosteriorSpam, Ham=$logPosteriorHam");

        // Convert back from log scale and normalize to get P(Spam|Text)
        // Using the log-sum-exp trick for numerical stability is better if values are extreme,
        // but direct exp and normalization often works for typical cases.
        // P(Spam | Text) = exp(logPostSpam) / (exp(logPostSpam) + exp(logPostHam))

        if ($logPosteriorSpam > $logPosteriorHam) {
             // Calculation when Spam is more likely: avoids exp(very large negative number)
             // exp(logPostHam - logPostSpam) is exp(negative) which is safer
             $ratio = exp($logPosteriorHam - $logPosteriorSpam);
             $probSpam = 1.0 / (1.0 + $ratio);
        } else {
             // Calculation when Ham is more likely (or equal)
             // exp(logPostSpam - logPostHam) is exp(negative or zero)
             $ratio = exp($logPosteriorSpam - $logPosteriorHam);
             $probSpam = $ratio / (1.0 + $ratio);
        }

         // Handle potential NaN if both posteriors are extremely small (e.g., -infinity)
         if (is_nan($probSpam)) {
              Log::warning("NaiveBayes: Prediction resulted in NaN. LogPostSpam: $logPosteriorSpam, LogPostHam: $logPosteriorHam. Returning 0.5.");
              return 0.5; // Fallback to neutral
         }

        // Log::debug("NaiveBayes: Final predicted spam probability: $probSpam");
        return $probSpam;
    }


    /**
     * Evaluates the classifier performance on a given set of messages.
     */
    public function evaluate(array $testMessages): array
    {
        if (!$this->isTrained) {
            Log::error("NaiveBayes: Cannot evaluate - classifier is not trained.");
            return ['accuracy' => 0, 'correct' => 0, 'total' => 0, 'predictions' => []];
        }

        $correct = 0;
        $total = count($testMessages);
        $predictions = [];

        if ($total === 0) {
            return ['accuracy' => 1, 'correct' => 0, 'total' => 0, 'predictions' => []]; // Or 0 accuracy? Define behavior for empty test set.
        }

        foreach ($testMessages as $msg) {
            if (!$msg instanceof TrainingMessage) continue;

            $probability = $this->predict($msg->text); // Predict probability ONCE
            $predictedIsSpam = $probability > 0.5; // Thresholding

            $predictions[] = [
                'text' => $msg->text,
                'actual_is_spam' => $msg->isSpam,
                'predicted_is_spam' => $predictedIsSpam,
                'spam_probability' => $probability
            ];

            if ($predictedIsSpam === $msg->isSpam) {
                $correct++;
            }
        }

        $accuracy = $correct / $total;
        Log::info("NaiveBayes: Evaluation complete. Accuracy: $accuracy ($correct/$total)");

        return [
            'accuracy' => $accuracy,
            'correct' => $correct,
            'total' => $total,
            'predictions' => $predictions // Contains detailed results for each message
        ];
    }

    /**
     * Saves the trained classifier state to a JSON file.
     */
    private function saveTrainedClassifier(): void
    {
        if (!$this->isTrained) {
             Log::warning("NaiveBayes: Attempted to save classifier, but it's not trained.");
             return;
        }

        $data = json_encode([
            'k' => $this->k,
            'numSpamMessages' => $this->numSpamMessages,
            'numHamMessages' => $this->numHamMessages,
            'wordCountsSpam' => $this->wordCountsSpam,
            'wordCountsHam' => $this->wordCountsHam,
            'totalWordsInSpam' => $this->totalWordsInSpam,
            'totalWordsInHam' => $this->totalWordsInHam,
            'vocabulary' => $this->vocabulary, // Save the vocabulary list
            // No need to save isTrained, it's determined by loading success
        ], JSON_PRETTY_PRINT); // Pretty print for readability

        if ($data === false) {
            Log::error("NaiveBayes: Failed to encode classifier data to JSON. Error: " . json_last_error_msg());
            return;
        }

        try {
            // Ensure directory exists
            $directory = dirname($this->storagePath);
             if (!Storage::disk('local')->exists($directory)) {
                  Storage::disk('local')->makeDirectory($directory);
                  Log::info("NaiveBayes: Created directory for classifier: " . $directory);
             }
             // Use Storage facade for saving
             Storage::disk('local')->put(str_replace(storage_path('app'), '', $this->storagePath), $data); // Path relative to storage/app
             Log::info("NaiveBayes: Trained classifier saved successfully to " . $this->storagePath);
        } catch (\Exception $e) {
            Log::error("NaiveBayes: Failed to save classifier file to {$this->storagePath}. Error: " . $e->getMessage());
        }
    }

    /**
     * Loads the trained classifier state from a JSON file.
     * Returns true on success, false otherwise.
     */
    public function loadTrainedClassifier(): bool
    {
        $relativePath = str_replace(storage_path('app'), '', $this->storagePath); // Path relative to storage/app

        if (!Storage::disk('local')->exists($relativePath)) {
            // Log::info("NaiveBayes: Classifier file not found at {$this->storagePath}."); // Less verbose maybe
            return false;
        }

        try {
            $jsonContent = Storage::disk('local')->get($relativePath);
            if ($jsonContent === null) {
                // This might happen if the file exists but is empty or unreadable by Storage facade
                Log::error("NaiveBayes: Classifier file exists but could not be read: {$this->storagePath}.");
                return false;
            }
            $data = json_decode($jsonContent, true); // Decode as associative array

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("NaiveBayes: Failed to decode JSON from classifier file {$this->storagePath}. Error: " . json_last_error_msg());
                return false;
            }

            // Validate required keys before assigning
            $requiredKeys = [
                'k', 'numSpamMessages', 'numHamMessages', 'wordCountsSpam',
                'wordCountsHam', 'totalWordsInSpam', 'totalWordsInHam', 'vocabulary'
            ];
            foreach ($requiredKeys as $key) {
                if (!isset($data[$key])) {
                    Log::error("NaiveBayes: Classifier file {$this->storagePath} is missing required key: '$key'. Load failed.");
                    return false;
                }
            }

            // Assign loaded data to properties
            $this->k = $data['k'];
            $this->numSpamMessages = $data['numSpamMessages'];
            $this->numHamMessages = $data['numHamMessages'];
            $this->wordCountsSpam = $data['wordCountsSpam'];
            $this->wordCountsHam = $data['wordCountsHam'];
            $this->totalWordsInSpam = $data['totalWordsInSpam'];
            $this->totalWordsInHam = $data['totalWordsInHam'];
            $this->vocabulary = $data['vocabulary'];
            $this->isTrained = true; // Mark as trained since loading succeeded

            // Optional: Add checks for data types/consistency if needed

            return true;

        } catch (\Exception $e) {
            Log::error("NaiveBayes: Failed to load or parse classifier file {$this->storagePath}. Error: " . $e->getMessage());
            $this->isTrained = false; // Ensure it's marked as not trained on failure
            return false;
        }
    }

     // Added getter to check if trained externally
     public function isTrained(): bool
     {
         return $this->isTrained;
     }
     public function storeModel(){
        $this->saveTrainedClassifier();
     }
}