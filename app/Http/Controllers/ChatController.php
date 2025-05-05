<?php
namespace App\Http\Controllers;

use App\Services\NaiveBayes;

class ChatController extends Controller
{
    // Static property to hold the classifier instance
    protected static $classifier;

    // Static method to get the NaiveBayes classifier instance
    public static function getClassifier()
    {
        if (!self::$classifier) {
            self::$classifier = new NaiveBayes();
        }

        return self::$classifier;
    }

    // Static method to predict spam
    public static function predictSpam($text): bool
    {
        $classifier = self::getClassifier();
        return $classifier->predict($text)>0.5;
    }
}
