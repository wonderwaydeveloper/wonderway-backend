<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseService
{
    public function getReadConnection()
    {
        try {
            return DB::connection('mysql');
        } catch (\Exception $e) {
            Log::warning('Read replica connection failed, falling back to write', [
                'error' => $e->getMessage(),
            ]);

            return DB::connection('mysql');
        }
    }

    public function getWriteConnection()
    {
        return DB::connection('mysql');
    }

    public function checkReplicationLag(): array
    {
        try {
            $writeConnection = $this->getWriteConnection();
            $readConnection = $this->getReadConnection();

            // Get master status
            $masterStatus = $writeConnection->select('SHOW MASTER STATUS')[0] ?? null;

            // Get slave status
            $slaveStatus = $readConnection->select('SHOW SLAVE STATUS')[0] ?? null;

            if (! $masterStatus || ! $slaveStatus) {
                return ['status' => 'unknown', 'lag' => null];
            }

            $lag = $slaveStatus->Seconds_Behind_Master ?? null;

            return [
                'status' => $lag !== null ? 'healthy' : 'error',
                'lag' => $lag,
                'master_file' => $masterStatus->File,
                'master_position' => $masterStatus->Position,
                'slave_file' => $slaveStatus->Master_Log_File,
                'slave_position' => $slaveStatus->Read_Master_Log_Pos,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check replication lag', ['error' => $e->getMessage()]);

            return ['status' => 'error', 'lag' => null, 'error' => $e->getMessage()];
        }
    }

    public function forceWriteConnection(\Closure $callback)
    {
        return DB::connection('mysql')->transaction($callback);
    }
}
