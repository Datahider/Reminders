# losthost/reminders

PHP библиотека для управления напоминаниями с поддержкой откладывания (snooze), массовых операций и периодических рассылок.

## Установка

```bash
composer require losthost/reminders
```

## Быстрый старт

```php
use losthost\Reminders\Reminder;
use losthost\Reminders\ReminderService;

// Инициализация таблицы
Reminder::initDataStructure();

// Создание напоминания
$reminder = Reminder::create(
    'user_123',              // ID объекта
    'telegram_bot',          // ID проекта  
    'Позвонить клиенту',     // Заголовок
    new DateTimeImmutable('+1 hour'), // Когда напомнить
    'Обсудить договор',      // Описание (опционально)
    'data1',                 // Доп. данные 1 (опционально)
    'data2'                  // Доп. данные 2 (опционально)
);

// Получение созревших напоминаний
$due = ReminderService::getDue();
while ($reminder = $due->next()) {
    // Отправить уведомление...
    $reminder->markDone();
}

// Отложить напоминание на 30 минут
$reminder->snooze(30);

// Отметить выполненным
$reminder->markDone();

// Отменить
$reminder->cancel();

// Сбросить на исходное время
$reminder->reset();
```

## Архитектура

### Сущности

**Reminder** - основная сущность:
- `id` - уникальный ID
- `object` - идентификатор объекта (VARCHAR(64))
- `project` - идентификатор проекта (VARCHAR(64))
- `subject` - заголовок
- `description` - описание
- `remind_at` - исходное время напоминания
- `remind_next` - следующее срабатывание (меняется при snooze)
- `status` - статус: pending/done/cancelled
- `notified_at` - когда отправлено уведомление (для клиента)
- `data1`, `data2` - резервные поля для клиента
- `created_at` - дата создания

### Особенности

- Автоматический `write()` при изменении полей существующих объектов
- Автоматический сброс `notified_at` при изменении `remind_next`
- Константы статусов для type safety
- Поддержка периодических рассылок через поле `notified_at`

## Сервисные методы

```php
// Получить все созревшие напоминания
ReminderService::getDue();

// Получить созревшие для конкретного объекта/проекта
ReminderService::getDueFor('user_123', 'telegram_bot');

// Получить созревшие неотправленные напоминания
ReminderService::getDueUnnotified();

// Получить список пар object/project с созревшими напоминаниями
ReminderService::getObjectsWithDue();

// Получить все напоминания для объекта/проекта
ReminderService::getFor('user_123', 'telegram_bot');

// Получить с фильтром по статусу
ReminderService::getFor('user_123', 'telegram_bot', Reminder::STATUS_PENDING);

// Количество pending напоминаний
ReminderService::countPending();
ReminderService::countPending('telegram_bot'); // по проекту

// Отменить все pending для объекта/проекта
ReminderService::cancelAllFor('user_123', 'telegram_bot');
```

## Использование для периодических рассылок

```php
// В кроне каждую минуту
$unnotified = ReminderService::getDueUnnotified();
while ($reminder = $unnotified->next()) {
    sendNotification($reminder);
    $reminder->notified_at = new DateTimeImmutable();
}
```

## Требования

- PHP 8.0+
- MySQL/MariaDB
- Библиотека [losthost/db](https://github.com/losthost/db)

## Лицензия

MIT

