<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException; 
use RuntimeException; 

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

    private int $numSpamMessages = 0;
    private int $numHamMessages = 0;
    private array $wordCountsSpam = [];
    private array $wordCountsHam = []; 
    private int $totalWordsInSpam = 0; 
    private int $totalWordsInHam = 0;  
    private array $vocabulary = [];    

    private string $storagePath;
    private bool $isTrained = false; 

    public function __construct(int $k = 1)
    {
        if ($k <= 0) {
             throw new InvalidArgumentException("Smoothing factor k must be positive.");
        }
        $this->k = $k;
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
            return;
        }
        if (!is_dir($dataPath . '/spam') || !is_dir($dataPath . '/ham')) {
             Log::error("NaiveBayes: Spam or Ham subdirectories missing in $dataPath. Cannot auto-train.");
             return;
        }

        $messages = $this->loadMessages($dataPath);
        if (empty($messages)) {
             Log::error('NaiveBayes: No messages loaded from dataset. Auto-training failed.');
             return;
        }

        $this->train($messages);
         if ($this->isTrained) {
             Log::info('NaiveBayes: Classifier auto-trained and saved successfully.');
         } else {
             Log::error('NaiveBayes: Auto-training completed, but the classifier is still not marked as trained. Check training logic.');
         }
    }


    public function tokenize(string $text): array
    {
        preg_match_all("/[a-z0-9]+/", strtolower($text), $matches);
        return array_filter($matches[0] ?? [], fn($word) => strlen($word) >= 3);
    }

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
                $subject = $this->extractSubject($content, $file);
                if (!empty($subject)) {
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
                     $messages[] = new TrainingMessage($subject, false);
                 } else {
                     Log::warning("NaiveBayes: Empty content/subject extracted from ham file: $file");
                 }
             }
        }


        Log::info("NaiveBayes: Loaded " . count($messages) . " total messages for training/evaluation.");
        shuffle($messages);
        return $messages;
    }

    private function extractSubject(string $content, string $filePath): string
    {
        $lines = explode("\n", $content, 2);
        if (isset($lines[0]) && str_starts_with(strtolower($lines[0]), 'subject:')) {
             $subject = trim(substr($lines[0], strlen('Subject:')));
             if(empty($subject)) {
                 Log::warning("NaiveBayes: Found 'Subject:' line but content is empty in file: $filePath. Using full content.");
                 return trim($content);
             }
             return $subject;
        }
         $trimmedContent = trim($content);
         if(empty($trimmedContent)) {
             Log::warning("NaiveBayes: No 'Subject:' line and content is empty in file: $filePath");
         } else {
             Log::warning("NaiveBayes: No 'Subject:' line found in file: $filePath. Using full trimmed content.");
         }
        return $trimmedContent;
    }

    public function trainTestSplit(array $messages, float $trainPct = 0.8): array
    {
        if ($trainPct <= 0 || $trainPct >= 1) {
            throw new InvalidArgumentException("Training percentage must be between 0 and 1.");
        }
        shuffle($messages);
        $numMessages = count($messages);
        if ($numMessages === 0) {
            return [[], []];
        }
        $numTrain = (int) floor($numMessages * $trainPct);
        if ($numTrain == 0 || $numTrain == $numMessages) {
             Log::warning("NaiveBayes: trainTestSplit resulted in an empty train or test set. Adjust percentage or dataset size.");
             if ($numMessages > 1) {
                  $numTrain = max(1, min($numMessages - 1, $numTrain));
             } else {
                  $numTrain = $numMessages;
             }

        }
        return [
            array_slice($messages, 0, $numTrain),
            array_slice($messages, $numTrain)    
        ];
    }

    public function train(array $trainingMessages): void
    {
         $this->numSpamMessages = 0;
         $this->numHamMessages = 0;
         $this->wordCountsSpam = [];
         $this->wordCountsHam = [];
         $this->totalWordsInSpam = 0;
         $this->totalWordsInHam = 0;
         $this->vocabulary = [];
         $this->isTrained = false;
         $vocabularySet = [];


         if (empty($trainingMessages)) {
              Log::error("NaiveBayes: Training failed - received empty list of messages.");
              return;
         }

         Log::info("NaiveBayes: Starting training with " . count($trainingMessages) . " messages.");

         foreach ($trainingMessages as $msg) {
             if (!$msg instanceof TrainingMessage) continue;

             $tokens = $this->tokenize($msg->text);

             if ($msg->isSpam) {
                 $this->numSpamMessages++;
                 foreach ($tokens as $token) {
                     $this->wordCountsSpam[$token] = ($this->wordCountsSpam[$token] ?? 0) + 1;
                     $this->totalWordsInSpam++;
                     $vocabularySet[$token] = true;
                 }
             } else {
                 $this->numHamMessages++;
                 foreach ($tokens as $token) {
                     $this->wordCountsHam[$token] = ($this->wordCountsHam[$token] ?? 0) + 1;
                     $this->totalWordsInHam++;
                     $vocabularySet[$token] = true;
                 }
             }
         }

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

    private function pWordSpam(string $word): float
    {
        $vocabSize = count($this->vocabulary);
        // Smoothed probability: (count(word in spam) + k) / (total words in spam + k * vocabulary size)
        $numerator = ($this->wordCountsSpam[$word] ?? 0) + $this->k;
        $denominator = $this->totalWordsInSpam + ($this->k * $vocabSize);

        if ($denominator == 0) {
            Log::error("NaiveBayes: Zero denominator calculating P(word|Spam) for '$word'. VocabSize: $vocabSize, TotalSpamWords: {$this->totalWordsInSpam}, k: {$this->k}");
             // Return a neutral probability or a very small number instead of erroring/returning 0
             return 1.0 / ($vocabSize + 1);
        }

        return $numerator / $denominator;
    }

    private function pWordHam(string $word): float
    {
        $vocabSize = count($this->vocabulary);
        $numerator = ($this->wordCountsHam[$word] ?? 0) + $this->k;
        $denominator = $this->totalWordsInHam + ($this->k * $vocabSize);

        if ($denominator == 0) {
            Log::error("NaiveBayes: Zero denominator calculating P(word|Ham) for '$word'. VocabSize: $vocabSize, TotalHamWords: {$this->totalWordsInHam}, k: {$this->k}");
             return 1.0 / ($vocabSize + 1);
        }

        return $numerator / $denominator;
    }

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

        $logPriorSpam = $this->numSpamMessages > 0 ? log($this->numSpamMessages / $totalMessages) : -1000.0;
        $logPriorHam = $this->numHamMessages > 0 ? log($this->numHamMessages / $totalMessages) : -1000.0;

        $logLikelihoodSpam = 0.0;
        $logLikelihoodHam = 0.0;

        $tokens = $this->tokenize($text);
        $vocabSet = array_flip($this->vocabulary);


        foreach ($tokens as $token) {
            if (isset($vocabSet[$token])) {
                $logLikelihoodSpam += log($this->pWordSpam($token));
                $logLikelihoodHam += log($this->pWordHam($token));
            }
        }

        $logPosteriorSpam = $logPriorSpam + $logLikelihoodSpam;
        $logPosteriorHam = $logPriorHam + $logLikelihoodHam;
        if ($logPosteriorSpam > $logPosteriorHam) {
             $ratio = exp($logPosteriorHam - $logPosteriorSpam);
             $probSpam = 1.0 / (1.0 + $ratio);
        } else {
             $ratio = exp($logPosteriorSpam - $logPosteriorHam);
             $probSpam = $ratio / (1.0 + $ratio);
        }

         if (is_nan($probSpam)) {
              Log::warning("NaiveBayes: Prediction resulted in NaN. LogPostSpam: $logPosteriorSpam, LogPostHam: $logPosteriorHam. Returning 0.5.");
              return 0.5; // Fallback to neutral
         }

        return $probSpam;
    }


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
            return ['accuracy' => 1, 'correct' => 0, 'total' => 0, 'predictions' => []];
        }

        foreach ($testMessages as $msg) {
            if (!$msg instanceof TrainingMessage) continue;

            $probability = $this->predict($msg->text);
            $predictedIsSpam = $probability > 0.5;

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
            'predictions' => $predictions 
        ];
    }

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
        ], JSON_PRETTY_PRINT); 

        if ($data === false) {
            Log::error("NaiveBayes: Failed to encode classifier data to JSON. Error: " . json_last_error_msg());
            return;
        }

        try {
            $directory = dirname($this->storagePath);
             if (!Storage::disk('local')->exists($directory)) {
                  Storage::disk('local')->makeDirectory($directory);
                  Log::info("NaiveBayes: Created directory for classifier: " . $directory);
             }
             Storage::disk('local')->put(str_replace(storage_path('app'), '', $this->storagePath), $data); // Path relative to storage/app
             Log::info("NaiveBayes: Trained classifier saved successfully to " . $this->storagePath);
        } catch (\Exception $e) {
            Log::error("NaiveBayes: Failed to save classifier file to {$this->storagePath}. Error: " . $e->getMessage());
        }
    }

    public function loadTrainedClassifier(): bool
    {
        $relativePath = str_replace(storage_path('app'), '', $this->storagePath);

        if (!Storage::disk('local')->exists($relativePath)) {
            return false;
        }

        try {
            $jsonContent = Storage::disk('local')->get($relativePath);
            if ($jsonContent === null) {
                Log::error("NaiveBayes: Classifier file exists but could not be read: {$this->storagePath}.");
                return false;
            }
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("NaiveBayes: Failed to decode JSON from classifier file {$this->storagePath}. Error: " . json_last_error_msg());
                return false;
            }

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

            $this->k = $data['k'];
            $this->numSpamMessages = $data['numSpamMessages'];
            $this->numHamMessages = $data['numHamMessages'];
            $this->wordCountsSpam = $data['wordCountsSpam'];
            $this->wordCountsHam = $data['wordCountsHam'];
            $this->totalWordsInSpam = $data['totalWordsInSpam'];
            $this->totalWordsInHam = $data['totalWordsInHam'];
            $this->vocabulary = $data['vocabulary'];
            $this->isTrained = true; // Mark as trained since loading succeeded
            return true;

        } catch (\Exception $e) {
            Log::error("NaiveBayes: Failed to load or parse classifier file {$this->storagePath}. Error: " . $e->getMessage());
            $this->isTrained = false; // Ensure it's marked as not trained on failure
            return false;
        }
    }

     public function isTrained(): bool
     {
         return $this->isTrained;
     }
     public function storeModel(){
        $this->saveTrainedClassifier();
     }
}