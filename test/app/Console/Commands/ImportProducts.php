<?php

namespace App\Console\Commands;

use App\Enums\Currency;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Psr\Log\LoggerInterface;

class ImportProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:import {file} {--test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from a CSV file';

    protected LoggerInterface $logger;


    /**
     * ImportProducts constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();

        $this->logger = $logger->channel('products_import');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');
        $testMode = $this->option('test');

        if (!file_exists($file)) {
            $this->error('File does not exist.');
            return;
        }

        $data = array_map('str_getcsv', file($file));
        $header = array_shift($data);

        $processed = 0;
        $successful = 0;
        $skipped = 0;

        $errorReport = [];

        foreach ($data as $row) {
            if (count($row) != count($header)) {
                $errorReport[] = [
                    'data' => $row,
                    'errors' => 'Invalid format! Row does not match header length'
                ];

                $this->logger->warning('Invalid format! Row does not match header length', [
                    'row' => $row,
                ]);
                $skipped++;
                continue;
            }

            $data = array_combine($header, array_map('trim', $row));

            $validator = Validator::make($data, [
                'Product Code' => 'required|string|max:255',
                'Product Name' => 'required|string|max:255',
                'Product Description' => 'nullable|string',
                'Stock' => 'nullable|integer',
                'Cost in GBP' => 'required|string',
                'Discontinued' => 'nullable|in:yes'
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errorReport[] = ['data' => $data, 'errors' => $validator->errors()->all()];

                $this->logger->warning('Validation failed', [
                    'data' => $data,
                    'errors' => $validator->errors()->all(),
                ]);

                continue;
            }

            $processed++;

            $discontinued = $data['Discontinued'] === 'yes';
            $stock = (int)$data['Stock'];
            $cost = (float)str_replace(['£', '$'], '', $data['Cost in GBP']);

            if (($cost < 5 && $stock < 10) || $cost > 1000) {
                $skipped++;

                $this->logger->info('Skipped product due to business logic', [
                    'data' => $data,
                    'reason' => 'Cost below 5 and stock below 10 or cost above 1000',
                ]);

                continue;
            }

            $productData = [
                'code' => $data['Product Code'],
                'name' => $data['Product Name'],
                'description' => $data['Product Description'] ?? '',
                'stock' => $stock,
                'cost' => $cost,
                'currency' => Currency::GBP,
                'is_discontinued' => $discontinued,
                'discontinued_date' => $discontinued ? now() : null,
            ];

            if (!$testMode) {
                try {
                    Product::query()->updateOrCreate(
                        ['code' => $productData['code']],
                        $productData
                    );

                    $successful++;

                    $this->logger->info('Product imported successfully', [
                        'product' => $productData,
                    ]);
                } catch (\Exception $e) {
                    $skipped++;
                    $errorReport[] = ['data' => $data, 'errors' => [$e->getMessage()]];

                    $this->logger->error('Error saving product', [
                        'data' => $data,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Summary
        $this->logger->info('Import Summary', [
            'processed' => $processed,
            'successful' => $successful,
            'skipped' => $skipped,
        ]);

        if (!empty($errorReport)) {
            $this->logger->info("Errors report: ");

            foreach ($errorReport as $error) {
                $this->logger->error('Error during import', [
                    'data' => $error['data'],
                    'errors' => $error['errors'],
                ]);
            }
        }

        return 0;
    }
}
