<?php

namespace Aurora\Modules\Contacts\Tests\Unit;

use Aurora\Modules\Contacts\Enums\SortField;
use Aurora\Modules\Contacts\Enums\StorageType;
use Aurora\Modules\Contacts\Models\ContactCard;
use Aurora\Modules\Contacts\Module;
use Aurora\System\Api;
use Aurora\System\Enums\SortOrder;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class FakeUser
{
    public $Id = 100;
    public $PublicId = 'user@example.com';
    public $Role = 0;
}

class FakeContactsDecorator
{
    public $contacts = [];
    public $lookups = [];
    public $created = [];
    public $getContactsCalls = 0;

    public function GetContactsByEmails($UserId, $Storage, $Emails, $AddressBookId = null)
    {
        $this->lookups[] = [$Storage, $Emails];

        return collect($this->contacts[$Storage] ?? [])->filter(function ($contact) use ($Emails) {
            return in_array(strtolower($contact->ViewEmail), $Emails, true);
        })->values();
    }

    public function CreateContact($Contact, $UserId)
    {
        $this->created[] = $Contact;
    }

    public function GetContacts()
    {
        $this->getContactsCalls++;

        return ['List' => []];
    }
}

class TestableModule extends Module
{
    public function collection($withTotalCount, $query)
    {
        return $this->getContactsCollection(SortField::Name, SortOrder::ASC, 0, 20, $query, $withTotalCount);
    }
}

class ContactsPerformanceTest extends TestCase
{
    private $module;
    private $decorator;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->schema()->create('contacts_cards', function ($table) {
            $table->increments('Id');
            $table->integer('CardId')->default(0);
            $table->string('FullName')->default('');
            $table->string('ViewEmail')->default('');
            $table->integer('Frequency')->default(0);
        });

        Api::skipCheckUserRole(true);
        Api::GetModuleManager();

        $this->decorator = new FakeContactsDecorator();
        Api::$aModuleDecorators[TestableModule::GetName()] = $this->decorator;
        $this->module = new TestableModule(dirname(__DIR__, 4) . '/');

        (new \ReflectionProperty(Api::class, 'usersCache'))->setValue(null, [100 => new FakeUser()]);
        (new \ReflectionProperty(Api::class, 'aUserSession'))->setValue(null, ['UserId' => 100, 'AuthToken' => '']);
        (new \ReflectionProperty(Api::class, 'oAuthenticatedUser'))->setValue(null, null);
    }

    protected function tearDown(): void
    {
        Api::skipCheckUserRole(false);
        Api::$aModuleDecorators[TestableModule::GetName()] = null;
        (new \ReflectionProperty(Api::class, 'usersCache'))->setValue(null, []);
        (new \ReflectionProperty(Api::class, 'aUserSession'))->setValue(null, []);
        (new \ReflectionProperty(Api::class, 'oAuthenticatedUser'))->setValue(null, null);
    }

    private function contact($cardId, $email, $frequency)
    {
        Capsule::table('contacts_cards')->insert(['CardId' => $cardId, 'ViewEmail' => $email, 'Frequency' => $frequency]);

        return (object) ['Id' => $cardId, 'ViewEmail' => $email, 'Frequency' => $frequency];
    }

    private function frequency($cardId)
    {
        return (int) Capsule::table('contacts_cards')->where('CardId', $cardId)->value('Frequency');
    }

    public function testTotalCountIsSelectedOnlyOnRequest()
    {
        $query = ContactCard::query();
        $this->module->collection(false, $query);
        $this->assertStringNotContainsString('TotalCount', $query->toSql());

        $query = ContactCard::query();
        $this->module->collection(true, $query);
        $this->assertStringContainsString('TotalCount', $query->toSql());
    }

    public function testSingleCharacterSearchDoesNotQueryContacts()
    {
        $result = $this->module->GetContactSuggestions(100, StorageType::All, 20, SortField::Name, SortOrder::ASC, 'a');

        $this->assertSame(['ContactCount' => 0, 'List' => []], $result);
        $this->assertSame(0, $this->decorator->getContactsCalls);
    }

    public function testEmptyAndLongerSearchQueryContacts()
    {
        $this->module->GetContactSuggestions(100, StorageType::All, 20, SortField::Name, SortOrder::ASC, '');
        $this->module->GetContactSuggestions(100, StorageType::All, 20, SortField::Name, SortOrder::ASC, 'ab');

        $this->assertSame(2, $this->decorator->getContactsCalls);
    }

    public function testRecipientsAreLookedUpOncePerStorage()
    {
        $this->decorator->contacts[StorageType::Personal] = [$this->contact(1, 'a@example.com', 1)];
        $this->decorator->contacts[StorageType::Collected] = [$this->contact(2, 'b@example.com', 5)];

        $result = null;
        $this->module->onAfterUseEmails(['IdUser' => 100, 'Emails' => [
            'a@example.com' => 'A',
            'b@example.com' => 'B',
            'c@example.com' => 'C',
        ]], $result);

        $this->assertSame([
            [StorageType::Personal, ['a@example.com', 'b@example.com', 'c@example.com']],
            [StorageType::Collected, ['b@example.com', 'c@example.com']],
        ], $this->decorator->lookups);
        $this->assertSame(2, $this->frequency(1));
        $this->assertSame(6, $this->frequency(2));
        $this->assertCount(1, $this->decorator->created);
        $this->assertSame('c@example.com', $this->decorator->created[0]['PersonalEmail']);
    }

    public function testPersonalContactWinsOverCollectedOne()
    {
        $this->decorator->contacts[StorageType::Personal] = [$this->contact(1, 'A@example.com', 1)];
        $this->decorator->contacts[StorageType::Collected] = [$this->contact(2, 'a@example.com', 5)];

        $result = null;
        $this->module->onAfterUseEmails(['IdUser' => 100, 'Emails' => ['a@example.com' => 'A']], $result);

        $this->assertCount(1, $this->decorator->lookups);
        $this->assertSame(2, $this->frequency(1));
        $this->assertSame(5, $this->frequency(2));
        $this->assertSame([], $this->decorator->created);
    }
}
