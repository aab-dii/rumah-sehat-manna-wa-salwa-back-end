<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;

class CreateDatabase extends Command
{
    protected $signature = 'db:create {name?}';
    protected $description = 'Create a new MySQL database';

    public function handle()
    {
        $databaseName = $this->argument('name') ?? config('database.connections.mysql.database');
        
        $charset = config('database.connections.mysql.charset', 'utf8mb4');
        $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s', 
                    config('database.connections.mysql.host'),
                    config('database.connections.mysql.port')
                ),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password')
            );

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET {$charset} COLLATE {$collation}");
            
            $this->info("Database '{$databaseName}' created successfully!");
            
            return Command::SUCCESS;
        } catch (PDOException $e) {
            $this->error("Failed to create database: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
