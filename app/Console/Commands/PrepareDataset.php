<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrepareDataset extends Command
{
    protected $signature = 'prepare:dataset {file}';
    protected $description = 'Convert SMS Spam Collection dataset to spam/ham directories';

    public function handle()
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File $file not found.");
            return;
        }

        Storage::disk('local')->deleteDirectory('dataset');
        Storage::disk('local')->makeDirectory('dataset/spam');
        Storage::disk('local')->makeDirectory('dataset/ham');

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $index => $line) {
            [$label, $text] = explode("\t", $line, 2);
            $dir = $label === 'spam' ? 'spam' : 'ham';
            Storage::disk('local')->put("dataset/$dir/$index.txt", "Subject: $text");
        }

        $this->info('Dataset prepared successfully.');
    }
}/