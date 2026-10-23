<?php

declare(strict_types=1);

namespace Phoenix\Tests\Database\Adapter\Pdo;

use InvalidArgumentException;
use PDO;
use Phoenix\Database\Adapter\Pdo\PdoStatementWrapper;
use Phoenix\Database\Adapter\StatementInterface;
use Phoenix\Database\QueryBuilder\QueryBuilderInterface;
use Phoenix\Exception\DatabaseQueryExecuteException;
use Phoenix\Tests\Helpers\Adapter\SqlitePdoAdapter;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Tests the shared PDO logic of the abstract PdoAdapter on an in-memory SQLite database.
 * Vendor specific adapters (MysqlAdapter, PgsqlAdapter) are tested in their own packages.
 */
final class PdoAdapterTest extends TestCase
{
    private const CREATE_TABLE = 'CREATE TABLE "phoenix_test_table" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "title" varchar(255) NOT NULL);';

    private const CREATE_TABLE_NULLABLE_TITLE = 'CREATE TABLE "phoenix_test_table" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "title" varchar(255) NULL);';

    private SqlitePdoAdapter $adapter;

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
        ]);
        $this->adapter = new SqlitePdoAdapter($pdo, $this->createMock(QueryBuilderInterface::class));
    }

    public function testCharsetAndCollation(): void
    {
        $this->assertNull($this->adapter->getCharset());
        $this->assertNull($this->adapter->getCollation());
        $this->assertSame($this->adapter, $this->adapter->setCharset('utf8'));
        $this->assertSame($this->adapter, $this->adapter->setCollation('utf8_general_ci'));
        $this->assertSame('utf8', $this->adapter->getCharset());
        $this->assertSame('utf8_general_ci', $this->adapter->getCollation());
    }

    public function testQueryReturnsPdoStatementWrapper(): void
    {
        $statement = $this->adapter->query('SELECT 1 AS one');
        $this->assertInstanceOf(PdoStatementWrapper::class, $statement);
        $this->assertSame([['one' => 1]], $statement->fetchAll());
    }

    public function testQueryError(): void
    {
        $this->expectException(DatabaseQueryExecuteException::class);
        $this->expectExceptionMessage('Query SELECT * FROM "phoenix_non_exist_test_table" fails');
        $this->adapter->query('SELECT * FROM "phoenix_non_exist_test_table"');
    }

    public function testExecuteAcceptsOnlyPdoStatementWrapper(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only ' . PdoStatementWrapper::class . ' can be executed by ' . SqlitePdoAdapter::class);
        $this->adapter->execute($this->createMock(StatementInterface::class));
    }

    public function testTransaction(): void
    {
        $this->assertInstanceOf(QueryBuilderInterface::class, $this->adapter->getQueryBuilder());

        $this->assertTrue($this->adapter->startTransaction());
        $this->adapter->query(self::CREATE_TABLE);
        $this->adapter->query('INSERT INTO "phoenix_test_table" VALUES (1, \'first\');');
        $this->adapter->query('INSERT INTO "phoenix_test_table" VALUES (2, \'second\');');
        $this->assertTrue($this->adapter->commit());
        $this->assertCount(2, $this->adapter->fetchAll('phoenix_test_table'));
    }

    public function testCommitAndRollbackWithoutTransaction(): void
    {
        $this->assertTrue($this->adapter->commit());
        $this->assertTrue($this->adapter->rollback());
    }

    public function testInsert(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $this->assertEquals(1, $this->adapter->insert('phoenix_test_table', ['id' => 1, 'title' => 'first']));
        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => 'second']));

        $this->assertCount(2, $this->adapter->fetchAll('phoenix_test_table'));
    }

    public function testBuildInsertQuery(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $statement = $this->adapter->buildInsertQuery('phoenix_test_table', ['id' => 1, 'title' => 'first']);
        $this->assertSame('INSERT INTO "phoenix_test_table" ("id", "title") VALUES (:id, :title);', $statement->getQueryString());

        $statement = $this->adapter->buildInsertQuery('phoenix_test_table', [['id' => 1, 'title' => 'first'], ['id' => 2, 'title' => 'second']]);
        $this->assertSame('INSERT INTO "phoenix_test_table" ("id", "title") VALUES (:item_0_id, :item_0_title), (:item_1_id, :item_1_title);', $statement->getQueryString());
    }

    public function testInsertToNonExistingTable(): void
    {
        $this->expectException(DatabaseQueryExecuteException::class);
        $this->adapter->insert('phoenix_non_exist_test_table', ['id' => 1, 'title' => 'first']);
    }

    public function testMultiInsert(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', [['id' => 1, 'title' => 'first'], ['id' => 2, 'title' => 'second']]));
        $this->assertCount(2, $this->adapter->fetchAll('phoenix_test_table'));
    }

    public function testUpdate(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $this->assertEquals(1, $this->adapter->insert('phoenix_test_table', ['id' => 1, 'title' => 'first']));
        $item = $this->adapter->fetch('phoenix_test_table', ['title'], ['id' => 1]);
        $this->assertEquals('first', $item['title']);

        $this->assertTrue($this->adapter->update('phoenix_test_table', ['id' => 1, 'title' => 'second'], ['id' => 1]));
        $item = $this->adapter->fetch('phoenix_test_table', ['title'], ['id' => 1], ['title']);
        $this->assertEquals('second', $item['title']);

        $this->assertTrue($this->adapter->update('phoenix_test_table', ['id' => 1, 'title' => 'third'], [], 'id = 1'));
        $items = $this->adapter->fetchAll('phoenix_test_table', ['title'], ['id' => 1], null, ['title' => 'DESC'], ['id']);
        $this->assertEquals('third', $items[0]['title']);

        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => 'fourth']));
        $this->assertTrue($this->adapter->update('phoenix_test_table', ['title' => 'multi update'], ['id' => [1, 2]]));

        $items = $this->adapter->fetchAll('phoenix_test_table', ['title'], ['id' => [1, 2]]);
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertEquals('multi update', $item['title']);
        }
    }

    public function testBuildUpdateQuery(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $statement = $this->adapter->buildUpdateQuery('phoenix_test_table', ['title' => 'second'], ['id' => 1]);
        $this->assertSame('UPDATE "phoenix_test_table" SET "title" = :title WHERE "id" = :where_id;', $statement->getQueryString());

        $statement = $this->adapter->buildUpdateQuery('phoenix_test_table', ['title' => 'second'], ['id' => [1, 2]], 'title <> \'x\'');
        $this->assertSame('UPDATE "phoenix_test_table" SET "title" = :title WHERE "id" IN (:where_0_id, :where_1_id) AND title <> \'x\';', $statement->getQueryString());
    }

    public function testUpdateItemInNonExistingTable(): void
    {
        $this->expectException(DatabaseQueryExecuteException::class);
        $this->adapter->update('phoenix_non_exist_test_table', ['id' => 1, 'title' => 'first']);
    }

    public function testSelect(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $this->assertEquals(1, $this->adapter->insert('phoenix_test_table', ['id' => 1, 'title' => 'first']));
        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => 'second']));

        $items = $this->adapter->select('SELECT * FROM "phoenix_test_table"');
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertCount(2, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only select query can be executed in select method');
        $this->adapter->select('INSERT INTO "phoenix_test_table" ("id", "title") VALUES (3, \'third\')');
    }

    public function testFetchAll(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $this->assertEquals(1, $this->adapter->insert('phoenix_test_table', ['id' => 1, 'title' => 'first']));
        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => 'second']));

        $items = $this->adapter->fetchAll('phoenix_test_table');
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertCount(2, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
        }

        $items = $this->adapter->fetchAll('phoenix_test_table', ['*'], [], '1', ['id' => 'DESC']);
        $this->assertCount(1, $items);
        $this->assertEquals(2, $items[0]['id']);
    }

    public function testFetchReturnsNullForMissingRow(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $this->assertNull($this->adapter->fetch('phoenix_test_table', ['*'], ['id' => 1]));
    }

    public function testFetchAllWithConditions(): void
    {
        $this->adapter->query(self::CREATE_TABLE_NULLABLE_TITLE);
        $this->assertEquals(1, $this->adapter->insert('phoenix_test_table', ['id' => 1, 'title' => 'first']));
        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => null]));

        $items = $this->adapter->fetchAll('phoenix_test_table', ['*'], ['title !=' => null]);
        $this->assertCount(1, $items);
        foreach ($items as $item) {
            $this->assertCount(2, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertNotNull($item['title']);
        }

        $items = $this->adapter->fetchAll('phoenix_test_table', ['*'], ['title' => null]);
        $this->assertCount(1, $items);
        foreach ($items as $item) {
            $this->assertCount(2, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertNull($item['title']);
        }

        $items = $this->adapter->fetchAll('phoenix_test_table', ['*'], ['id >' => 1]);
        $this->assertCount(1, $items);
        foreach ($items as $item) {
            $this->assertCount(2, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertGreaterThan(1, $item['id']);
        }

        $items = $this->adapter->fetchAll('phoenix_test_table', ['*'], ['id >=' => 1]);
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertCount(2, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertGreaterThanOrEqual(1, $item['id']);
        }

        $items = $this->adapter->fetchAll('phoenix_test_table', ['*'], ['id <' => 2]);
        $this->assertCount(1, $items);
        foreach ($items as $item) {
            $this->assertCount(2, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertLessThan(2, $item['id']);
        }

        $items = $this->adapter->fetchAll('phoenix_test_table', ['*'], ['id <=' => 2]);
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertCount(2, $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertLessThanOrEqual(2, $item['id']);
        }

        $items = $this->adapter->fetchAll('phoenix_test_table', ['*'], ['id !=' => [1]]);
        $this->assertCount(1, $items);
        $this->assertEquals(2, $items[0]['id']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot accept "<" operator for NULL value');
        $this->adapter->fetchAll('phoenix_test_table', ['*'], ['title <' => null]);
    }

    public function testFetchAllWithUnsupportedOperatorForListValue(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot accept ">" operator for list value');
        $this->adapter->fetchAll('phoenix_test_table', ['*'], ['id >' => [1, 2]]);
    }

    public function testDelete(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $this->assertEquals(1, $this->adapter->insert('phoenix_test_table', ['id' => 1, 'title' => 'first']));
        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => 'second']));

        $items = $this->adapter->fetchAll('phoenix_test_table');
        $this->assertCount(2, $items);

        $this->adapter->delete('phoenix_test_table');
        $items = $this->adapter->fetchAll('phoenix_test_table');
        $this->assertCount(0, $items);

        $this->assertEquals(1, $this->adapter->insert('phoenix_test_table', ['id' => 1, 'title' => 'first']));
        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => 'second']));
        $items = $this->adapter->fetchAll('phoenix_test_table');
        $this->assertCount(2, $items);

        $this->adapter->delete('phoenix_test_table', ['id' => 2]);
        $items = $this->adapter->fetchAll('phoenix_test_table');
        $this->assertCount(1, $items);

        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => 'second']));
        $this->adapter->delete('phoenix_test_table', [], 'id < 3');
        $items = $this->adapter->fetchAll('phoenix_test_table');
        $this->assertCount(0, $items);

        $this->assertEquals(1, $this->adapter->insert('phoenix_test_table', ['id' => 1, 'title' => 'first']));
        $this->assertEquals(2, $this->adapter->insert('phoenix_test_table', ['id' => 2, 'title' => 'second']));
        $items = $this->adapter->fetchAll('phoenix_test_table');
        $this->assertCount(2, $items);

        $this->adapter->delete('phoenix_test_table', ['id' => [1, 2]]);
        $this->assertCount(0, $this->adapter->fetchAll('phoenix_test_table'));
    }

    public function testBuildDeleteQuery(): void
    {
        $this->adapter->query(self::CREATE_TABLE);
        $statement = $this->adapter->buildDeleteQuery('phoenix_test_table', ['id' => 1], 'title IS NOT NULL');
        $this->assertSame('DELETE FROM "phoenix_test_table" WHERE "id" = :where_id AND title IS NOT NULL;', $statement->getQueryString());
    }

    public function testDeleteFromNonExistingTable(): void
    {
        $this->expectException(DatabaseQueryExecuteException::class);
        $this->adapter->delete('phoenix_non_exist_test_table');
    }

    public function testRollback(): void
    {
        $this->assertTrue($this->adapter->startTransaction());
        $this->adapter->query(self::CREATE_TABLE);
        try {
            $this->adapter->query(self::CREATE_TABLE);
            $this->fail('Second CREATE TABLE of the same table has to fail');
        } catch (DatabaseQueryExecuteException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
            $this->assertStringEndsWith('Query ' . self::CREATE_TABLE . ' fails', $e->getMessage());
            $this->assertTrue($this->adapter->rollback());
        }

        $this->expectException(DatabaseQueryExecuteException::class);
        $this->adapter->fetchAll('phoenix_test_table');
    }
}
