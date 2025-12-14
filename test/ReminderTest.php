<?php

use PHPUnit\Framework\TestCase;
use losthost\Reminders\Reminder;
use losthost\Reminders\ReminderService;
use losthost\DB\DB;
use losthost\DB\DBValue;

class ReminderTest extends TestCase
{
    
    protected function setUp(): void
    {
        DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF);
        DB::dropAllTables(true, true);
        Reminder::initDataStructure(true);
    }
    
    public function testCreateReturnsObject()
    {
        $remindAt = new DateTimeImmutable('+1 hour');
        $reminder = Reminder::create(
            'user_123',
            'telegram_bot',
            'Позвонить клиенту',
            $remindAt,
            'Обсудить договор',
            'data1_value',
            'data2_value'
        );
        
        $this->assertInstanceOf(Reminder::class, $reminder);
        $this->assertGreaterThan(0, $reminder->id);
        
        $this->assertEquals('user_123', $reminder->object);
        $this->assertEquals('telegram_bot', $reminder->project);
        $this->assertEquals('Позвонить клиенту', $reminder->subject);
        $this->assertEquals('Обсудить договор', $reminder->description);
        
        $this->assertEquals($remindAt->format('Y-m-d H:i:s'), $reminder->remind_at->format('Y-m-d H:i:s'));
        $this->assertEquals($remindAt->format('Y-m-d H:i:s'), $reminder->remind_next->format('Y-m-d H:i:s'));

        $this->assertEquals(Reminder::STATUS_PENDING, $reminder->status);
        $this->assertEquals('data1_value', $reminder->data1);
        $this->assertEquals('data2_value', $reminder->data2);
        $this->assertNotNull($reminder->created_at);
    }
    
    public function testSnoozeChangesRemindNext()
    {
        $remindAt = new DateTimeImmutable('+1 hour');
        $reminder = Reminder::create('user1', 'project1', 'Test', $remindAt);

        $originalNext = $reminder->remind_next;
        $beforeSnooze = new DateTimeImmutable();
        $reminder->snooze(30);
        $afterSnooze = new DateTimeImmutable();

        $this->assertNotEquals($originalNext, $reminder->remind_next);
        $this->assertEquals(Reminder::STATUS_PENDING, $reminder->status);

        // Проверяем что remind_next примерно на 30 минут вперёд от момента snooze
        $minExpected = $beforeSnooze->modify('+30 minutes');
        $maxExpected = $afterSnooze->modify('+30 minutes');

        $this->assertGreaterThanOrEqual($minExpected->getTimestamp(), $reminder->remind_next->getTimestamp());
        $this->assertLessThanOrEqual($maxExpected->getTimestamp(), $reminder->remind_next->getTimestamp());
    }
    
    public function testMarkDoneChangesStatus()
    {
        $reminder = Reminder::create('user1', 'project1', 'Test', new DateTimeImmutable('+1 hour'));
        
        $reminder->markDone();
        
        $this->assertEquals(Reminder::STATUS_DONE, $reminder->status);
    }
    
    public function testCancelChangesStatus()
    {
        $reminder = Reminder::create('user1', 'project1', 'Test', new DateTimeImmutable('+1 hour'));
        
        $reminder->cancel();
        
        $this->assertEquals(Reminder::STATUS_CANCELLED, $reminder->status);
    }
    
    public function testResetReturnsToOriginalTime()
    {
        $remindAt = new DateTimeImmutable('+1 hour');
        $reminder = Reminder::create('user1', 'project1', 'Test', $remindAt);
        
        $reminder->snooze(30);
        $reminder->reset();
        
        $this->assertEquals($remindAt->format('Y-m-d H:i:s'), $reminder->remind_next->format('Y-m-d H:i:s'));
        $this->assertEquals(Reminder::STATUS_PENDING, $reminder->status);
    }
    
    public function testAutoWriteOnFieldChange()
    {
        $reminder = Reminder::create('user1', 'project1', 'Test', new DateTimeImmutable('+1 hour'));
        $id = $reminder->id;
        
        $reminder->subject = 'Updated subject';
        
        // Проверяем что записалось в БД
        $sql = "SELECT subject FROM [reminders] WHERE id = :id";
        $result = new DBValue($sql, ['id' => $id]);
        $this->assertEquals('Updated subject', $result->subject);
    }
    
    public function testAutoResetNotifiedAtOnRemindNextChange()
    {
        $reminder = Reminder::create('user1', 'project1', 'Test', new DateTimeImmutable('+1 hour'));
        $reminder->notified_at = new DateTimeImmutable();
        
        $reminder->snooze(30);
        
        $this->assertNull($reminder->notified_at);
    }
    
    public function testGetDueReturnsPendingReminders()
    {
        $past = new DateTimeImmutable('-1 hour');
        $future = new DateTimeImmutable('+1 hour');
        
        Reminder::create('user1', 'project1', 'Past due', $past);
        Reminder::create('user2', 'project1', 'Future', $future);
        
        $due = ReminderService::getDue();
        
        $count = 0;
        while ($reminder = $due->next()) {
            $count++;
            $this->assertLessThanOrEqual(new DateTimeImmutable(), $reminder->remind_next);
            $this->assertEquals(Reminder::STATUS_PENDING, $reminder->status);
        }
        
        $this->assertEquals(1, $count);
    }
    
    public function testGetDueForSpecificObjectProject()
    {
        $past = new DateTimeImmutable('-1 hour');
        
        Reminder::create('user1', 'project1', 'Test1', $past);
        Reminder::create('user1', 'project2', 'Test2', $past);
        Reminder::create('user2', 'project1', 'Test3', $past);
        
        $due = ReminderService::getDueFor('user1', 'project1');
        
        $count = 0;
        while ($reminder = $due->next()) {
            $count++;
            $this->assertEquals('user1', $reminder->object);
            $this->assertEquals('project1', $reminder->project);
        }
        
        $this->assertEquals(1, $count);
    }
    
    public function testGetDueUnnotified()
    {
        $past = new DateTimeImmutable('-1 hour');
        
        $r1 = Reminder::create('user1', 'project1', 'Test1', $past);
        $r2 = Reminder::create('user2', 'project1', 'Test2', $past);
        
        $r1->notified_at = new DateTimeImmutable();
        
        $unnotified = ReminderService::getDueUnnotified();
        
        $count = 0;
        while ($reminder = $unnotified->next()) {
            $count++;
            $this->assertNull($reminder->notified_at);
        }
        
        $this->assertEquals(1, $count);
    }
    
    public function testGetObjectsWithDue()
    {
        $past = new DateTimeImmutable('-1 hour');
        
        Reminder::create('user1', 'project1', 'Test1', $past);
        Reminder::create('user1', 'project1', 'Test2', $past);
        Reminder::create('user2', 'project1', 'Test3', $past);
        Reminder::create('user1', 'project2', 'Test4', $past);
        
        $objects = ReminderService::getObjectsWithDue();
        
        $this->assertCount(3, $objects);
        
        $expected = [
            ['object' => 'user1', 'project' => 'project1'],
            ['object' => 'user2', 'project' => 'project1'],
            ['object' => 'user1', 'project' => 'project2']
        ];
        
        foreach ($expected as $pair) {
            $this->assertContains($pair, $objects);
        }
    }
    
    public function testGetForObjectProject()
    {
        Reminder::create('user1', 'project1', 'Test1', new DateTimeImmutable('+1 hour'));
        Reminder::create('user1', 'project1', 'Test2', new DateTimeImmutable('+2 hours'));
        Reminder::create('user2', 'project1', 'Test3', new DateTimeImmutable('+1 hour'));
        
        $list = ReminderService::getFor('user1', 'project1');
        
        $count = 0;
        while ($reminder = $list->next()) {
            $count++;
            $this->assertEquals('user1', $reminder->object);
            $this->assertEquals('project1', $reminder->project);
        }
        
        $this->assertEquals(2, $count);
    }
    
    public function testGetForWithStatusFilter()
    {
        $r1 = Reminder::create('user1', 'project1', 'Test1', new DateTimeImmutable('+1 hour'));
        $r2 = Reminder::create('user1', 'project1', 'Test2', new DateTimeImmutable('+2 hours'));
        
        $r1->markDone();
        
        $pending = ReminderService::getFor('user1', 'project1', Reminder::STATUS_PENDING);
        $done = ReminderService::getFor('user1', 'project1', Reminder::STATUS_DONE);
        
        $pendingCount = 0;
        while ($reminder = $pending->next()) {
            $pendingCount++;
            $this->assertEquals(Reminder::STATUS_PENDING, $reminder->status);
        }
        
        $doneCount = 0;
        while ($reminder = $done->next()) {
            $doneCount++;
            $this->assertEquals(Reminder::STATUS_DONE, $reminder->status);
        }
        
        $this->assertEquals(1, $pendingCount);
        $this->assertEquals(1, $doneCount);
    }
    
    public function testCountPending()
    {
        Reminder::create('user1', 'project1', 'Test1', new DateTimeImmutable('+1 hour'));
        Reminder::create('user1', 'project2', 'Test2', new DateTimeImmutable('+2 hours'));
        Reminder::create('user2', 'project1', 'Test3', new DateTimeImmutable('+1 hour'));
        
        $r = Reminder::create('user1', 'project1', 'Test4', new DateTimeImmutable('+1 hour'));
        $r->markDone();
        
        $total = ReminderService::countPending();
        $project1 = ReminderService::countPending('project1');
        
        $this->assertEquals(3, $total);
        $this->assertEquals(2, $project1); // user1+project1 (1 pending) + user2+project1 (1 pending)
    }
    
    public function testCancelAllFor()
    {
        Reminder::create('user1', 'project1', 'Test1', new DateTimeImmutable('+1 hour'));
        Reminder::create('user1', 'project1', 'Test2', new DateTimeImmutable('+2 hours'));
        Reminder::create('user2', 'project1', 'Test3', new DateTimeImmutable('+1 hour'));
        
        $cancelled = ReminderService::cancelAllFor('user1', 'project1');
        
        $this->assertEquals(2, $cancelled);
        
        $sql = "SELECT COUNT(*) as cnt FROM [reminders] 
                WHERE object = 'user1' AND project = 'project1' AND status = 'cancelled'";
        $result = new DBValue($sql);
        $this->assertEquals(2, $result->cnt);
    }
    
    public function testStatusConstants()
    {
        $this->assertEquals('pending', Reminder::STATUS_PENDING);
        $this->assertEquals('done', Reminder::STATUS_DONE);
        $this->assertEquals('cancelled', Reminder::STATUS_CANCELLED);
    }
    
    public function testGetDueWithCustomNow()
    {
        $now = new DateTimeImmutable('2024-01-01 12:00:00');
        $past = new DateTimeImmutable('2024-01-01 11:00:00');
        $future = new DateTimeImmutable('2024-01-01 13:00:00');
        
        Reminder::create('user1', 'project1', 'Past', $past);
        Reminder::create('user2', 'project1', 'Future', $future);
        
        $due = ReminderService::getDue($now);
        
        $count = 0;
        while ($reminder = $due->next()) {
            $count++;
            $this->assertEquals('Past', $reminder->subject);
        }
        
        $this->assertEquals(1, $count);
    }
    
    public function testGetDueUnnotifiedFor()
    {
        $past = new DateTimeImmutable('-1 hour');
        
        $r1 = Reminder::create('user1', 'project1', 'Test1', $past);
        $r2 = Reminder::create('user1', 'project1', 'Test2', $past);
        $r3 = Reminder::create('user2', 'project1', 'Test3', $past);
        
        $r1->notified_at = new DateTimeImmutable();
        
        $unnotified = ReminderService::getDueUnnotifiedFor('user1', 'project1');
        
        $count = 0;
        while ($reminder = $unnotified->next()) {
            $count++;
            $this->assertEquals('user1', $reminder->object);
            $this->assertEquals('project1', $reminder->project);
            $this->assertNull($reminder->notified_at);
        }
        
        $this->assertEquals(1, $count);
    }
}
