<?php

declare(strict_types=1);

namespace Phoenix\Tests\Database\Adapter\Pdo;

use PDO;
use Phoenix\Database\Adapter\Pdo\PdoStatementWrapper;
use Phoenix\Database\Adapter\StatementInterface;
use PHPUnit\Framework\TestCase;

final class PdoStatementWrapperTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, title TEXT NOT NULL)');
        $this->pdo->exec("INSERT INTO items (id, title) VALUES (1, 'first'), (2, 'second')");
    }

    public function testImplementsStatementInterface(): void
    {
        $statement = new PdoStatementWrapper($this->pdo->prepare('SELECT 1'));
        $this->assertInstanceOf(StatementInterface::class, $statement);
    }

    public function testQueryStringAndNativeStatement(): void
    {
        $pdoStatement = $this->pdo->prepare('SELECT id FROM items');
        $statement = new PdoStatementWrapper($pdoStatement);
        $this->assertSame('SELECT id FROM items', $statement->getQueryString());
        $this->assertSame('SELECT id FROM items', (string) $statement);
        $this->assertSame($pdoStatement, $statement->getPdoStatement());
    }

    public function testFetchAllReturnsAssociativeRows(): void
    {
        $statement = new PdoStatementWrapper($this->pdo->prepare('SELECT id, title FROM items ORDER BY id'));
        $this->assertTrue($statement->execute());
        $this->assertSame([
            ['id' => 1, 'title' => 'first'],
            ['id' => 2, 'title' => 'second'],
        ], $statement->fetchAll());
    }

    public function testFetchReturnsRowOrNull(): void
    {
        $statement = new PdoStatementWrapper($this->pdo->prepare('SELECT id, title FROM items WHERE id = :id'));
        $statement->bindValue('id', 2);
        $statement->execute();
        $this->assertSame(['id' => 2, 'title' => 'second'], $statement->fetch());
        $this->assertNull($statement->fetch());
    }

    public function testFetchColumn(): void
    {
        $statement = new PdoStatementWrapper($this->pdo->prepare('SELECT id, title FROM items ORDER BY id'));
        $statement->execute();
        $this->assertSame(1, $statement->fetchColumn());
        $this->assertSame('second', $statement->fetchColumn(1));
        $this->assertFalse($statement->fetchColumn());
    }
}
