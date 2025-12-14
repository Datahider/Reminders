<?php

namespace losthost\Reminders;

use losthost\DB\DBList;
use losthost\DB\DBValue;
use losthost\DB\DBView;
use losthost\DB\DB;

class ReminderService {
    
    /**
     * Получить созревшие напоминания
     */
    public static function getDue(?\DateTimeInterface $now = null): DBList {
        $now = $now ?? new \DateTimeImmutable();
        return new DBList(
            Reminder::class,
            'status = ? AND remind_next <= ? ORDER BY remind_next ASC',
            [Reminder::STATUS_PENDING, $now]
        );
    }
    
    /**
     * Получить созревшие напоминания для объекта/проекта
     */
    public static function getDueFor(string $object, string $project): DBList {
        $now = new \DateTimeImmutable();
        return new DBList(
            Reminder::class,
            'object = ? AND project = ? AND status = ? AND remind_next <= ? ORDER BY remind_next ASC',
            [$object, $project, Reminder::STATUS_PENDING, $now]
        );
    }
    
    /**
     * Получить созревшие неотправленные напоминания
     */
    public static function getDueUnnotified(): DBList {
        $now = new \DateTimeImmutable();
        return new DBList(
            Reminder::class,
            'status = ? AND remind_next <= ? AND notified_at IS NULL ORDER BY remind_next ASC',
            [Reminder::STATUS_PENDING, $now]
        );
    }
    
    /**
     * Получить созревшие неотправленные напоминания для объекта/проекта
     */
    public static function getDueUnnotifiedFor(string $object, string $project): DBList {
        $now = new \DateTimeImmutable();
        return new DBList(
            Reminder::class,
            'object = ? AND project = ? AND status = ? AND remind_next <= ? AND notified_at IS NULL ORDER BY remind_next ASC',
            [$object, $project, Reminder::STATUS_PENDING, $now]
        );
    }
    
    /**
     * Получить список пар object/project, у которых есть созревшие напоминания
     */
    public static function getObjectsWithDue(): array {
        $sql = 'SELECT DISTINCT object, project FROM [reminders] WHERE status = ? AND remind_next <= ?';
        
        $view = new DBView($sql, [Reminder::STATUS_PENDING, date_create()->format(DB::DATE_FORMAT)]); 
        
        $pairs = [];
        while ($view->next()) {
            $pairs[] = ['object' => $view->object, 'project' => $view->project];
        }
        return $pairs;
    }
    
    /**
     * Получить напоминания для объекта/проекта
     */
    public static function getFor(
        string $object,
        string $project,
        ?string $status = null
    ): DBList {
        if ($status) {
            return new DBList(
                Reminder::class,
                ['object' => $object, 'project' => $project, 'status' => $status]
            );
        }
        return new DBList(
            Reminder::class,
            ['object' => $object, 'project' => $project]
        );
    }
    
    /**
     * Количество pending напоминаний
     */
    public static function countPending(?string $project = null): int {
        if ($project) {
            $sql = 'SELECT COUNT(*) as cnt FROM [reminders] WHERE status = ? AND project = ?';
            return (int) DBValue::new($sql, [Reminder::STATUS_PENDING, $project])->cnt;
        }
        $sql = 'SELECT COUNT(*) as cnt FROM [reminders] WHERE status = ?';
        return (int) DBValue::new($sql, [Reminder::STATUS_PENDING])->cnt;
    }
    
    /**
     * Отменить все pending для объекта/проекта
     */
    public static function cancelAllFor(string $object, string $project): int {
        $count = 0;
        $list = static::getFor($object, $project, Reminder::STATUS_PENDING);
        while ($reminder = $list->next()) {
            $reminder->cancel();
            $count++;
        }
        return $count;
    }
}
