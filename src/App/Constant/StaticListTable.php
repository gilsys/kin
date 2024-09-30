<?php

declare(strict_types=1);

namespace App\Constant;

class StaticListTable
{

    const ClientType = 'CT';
    const Country = 'CR';
    const ProcessType = 'PT';
    const ProcessStatus = 'PS';
    const TaskType = 'TT';
    const TaskStatus = 'TS';
    const TaskPaymentStatus = 'TPS';

    public static function getEntity($key)
    {
        $mapping = [
            self::ClientType => 'client_type',
            self::Country => 'country',
            self::ProcessType => 'process_type',
            self::ProcessStatus => 'process_status',
            self::TaskType => 'task_type',
            self::TaskStatus => 'task_status',
            self::TaskPaymentStatus => 'task_payment_status',
        ];

        if (empty($mapping[$key])) {
            throw new \Exception(__('app.error.invalid_parameters'));
        }

        return $mapping[$key];
    }

    public static function getTable($key)
    {
        return 'st_' . self::getEntity($key);
    }
}
