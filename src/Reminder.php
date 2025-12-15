<?php

namespace losthost\Reminders;

use losthost\DB\DBObject;
use losthost\DB\DB;

class Reminder extends DBObject {
    
    const STATUS_PENDING = 'pending';
    const STATUS_DONE = 'done';
    const STATUS_CANCELLED = 'cancelled';
    
    const METADATA = [
        'id'          => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
        'object'      => 'VARCHAR(64) NOT NULL COMMENT "ID объекта"',
        'project'     => 'VARCHAR(64) NOT NULL COMMENT "ID проекта"',
        'subject'     => 'VARCHAR(255) NOT NULL COMMENT "Заголовок"',
        'description' => 'TEXT COMMENT "Описание"',
        'remind_at'   => 'DATETIME NOT NULL COMMENT "Исходное время"',
        'remind_next' => 'DATETIME NOT NULL COMMENT "Следующее срабатывание"',
        'status'      => 'VARCHAR(20) NOT NULL DEFAULT "pending"',
        'notified_at' => 'DATETIME COMMENT "Для клиента - когда отправлено уведомление"',
        'data1'       => 'VARCHAR(100) COMMENT "Данные клиента 1"',
        'data2'       => 'VARCHAR(100) COMMENT "Данные клиента 2"',
        'created_at'  => 'DATETIME NOT NULL',
        'PRIMARY KEY' => 'id',
        'INDEX idx_lookup'  => ['object', 'project'],
        'INDEX idx_pending' => ['status', 'remind_next']
    ];
    
    public static function tableName(): string {
        return DB::$prefix . 'reminders';
    }
    
    /**
     * Автоматический write при изменении полей существующего объекта
     * Сброс notified_at при изменении remind_next
     */
    public function __set($name, $value) {
        $old = $this->$name ?? null;
        parent::__set($name, $value);
        
        if (!$this->isNew() && $old !== $value && $this->isModified()) {
            // Сброс notified_at при изменении remind_next
            if ($name === 'remind_next' && $old !== $value) {
                $this->notified_at = null;
            }
            $this->write();
        }
    }
    
    /**
     * Создать напоминание
     */
    public static function create(
        string $object,
        string $project,
        string $subject,
        \DateTimeInterface $remindAt,
        ?string $description = null,
        ?string $data1 = null,
        ?string $data2 = null
    ): static {
        $reminder = new static();
        $reminder->object = $object;
        $reminder->project = $project;
        $reminder->subject = $subject;
        $reminder->description = $description;
        $reminder->remind_at = $remindAt;
        $reminder->remind_next = $remindAt;
        $reminder->status = self::STATUS_PENDING;
        $reminder->data1 = $data1;
        $reminder->data2 = $data2;
        $reminder->created_at = new \DateTimeImmutable();
        $reminder->write();
        return $reminder;
    }
    
    /**
     * Отложить напоминание
     */
    public function snooze(int $minutes): void {
        $this->remind_next = new \DateTimeImmutable('+' . $minutes . ' minutes');
        $this->status = self::STATUS_PENDING;
    }
    
    /**
     * Отметить выполненным
     */
    public function markDone(): void {
        $this->status = self::STATUS_DONE;
    }
    
    /**
     * Отменить
     */
    public function cancel(): void {
        $this->status = self::STATUS_CANCELLED;
    }
    
    /**
     * Сбросить на исходное время
     */
    public function reset(): void {
        $this->remind_next = $this->remind_at;
        $this->status = self::STATUS_PENDING;
    }
}
