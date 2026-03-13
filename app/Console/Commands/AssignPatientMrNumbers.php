<?php

namespace App\Console\Commands;

use App\Models\Patient;
use Illuminate\Console\Command;

class AssignPatientMrNumbers extends Command
{
    protected $signature = 'patient:assign-mr-numbers';

    protected $description = 'Assign MR numbers to patients that do not have one';

    public function handle(): int
    {
        $count = Patient::query()->whereNull('mr_number')->count();
        if ($count === 0) {
            $this->info('All patients already have MR numbers.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        Patient::query()
            ->whereNull('mr_number')
            ->each(function (Patient $patient) use ($bar): void {
                $patient->mr_number = Patient::generateMrNumber();
                $patient->saveQuietly();
                $bar->advance();
            });

        $bar->finish();
        $this->newLine();
        $this->info("Assigned MR numbers to {$count} patient(s).");

        return self::SUCCESS;
    }
}
