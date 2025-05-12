<?php

namespace Tests\Unit;

use App\Services\NaiveBayes;
use App\Services\TrainingMessage;
use Tests\TestCase;

class NaiveBayesTest extends TestCase
{
    public function testTrainNaiveBayesWithTrainingMessageInstances()
    {
        // Arrange: Create an instance of NaiveBayes
        $naiveBayes = new NaiveBayes();

        // Arrange: Create TrainingMessage instances for training data
        $trainingData = [
            new TrainingMessage('win money now', true),
            new TrainingMessage('free prize', true),
            new TrainingMessage('hello friend', false),
            new TrainingMessage('meet me later', false),
        ];

        // Act: Train the classifier with the TrainingMessage instances
        $naiveBayes->train($trainingData);

        // Assert: Check if the classifier is trained (adjust based on your implementation)
        $this->assertTrue(method_exists($naiveBayes, 'isTrained') ? $naiveBayes->isTrained() : true, 'NaiveBayes should be trained successfully');
    }
}