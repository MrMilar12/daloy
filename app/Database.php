<?php
declare(strict_types=1);
final class Database {
    public PDO $pdo;
    public function __construct(array $config) {
        $this->pdo = new PDO($config['dsn'], $config['username'] ?? null, $config['password'] ?? null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') $this->pdo->exec('PRAGMA foreign_keys=ON');
    }
    public function all(string $sql, array $params=[]): array { $s=$this->pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    public function one(string $sql, array $params=[]): ?array { return $this->all($sql,$params)[0] ?? null; }
    public function run(string $sql, array $params=[]): int { $s=$this->pdo->prepare($sql); $s->execute($params); return $s->rowCount(); }
    public function insert(string $table, array $data): int {
        $keys=array_keys($data); $this->run('INSERT INTO '.$table.' ('.implode(',',$keys).') VALUES ('.implode(',',array_fill(0,count($keys),'?')).')',array_values($data));
        return (int)$this->pdo->lastInsertId();
    }
    public function update(string $table, int $id, array $data): void {
        $this->run('UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$k.'=?',array_keys($data))).' WHERE id=?',[...array_values($data),$id]);
    }
    public function transaction(callable $fn): mixed {
        $this->pdo->beginTransaction();
        try {
            // One short write lock serializes workforce decisions, including leave approval.
            $this->run("UPDATE system_settings SET setting_value=setting_value WHERE setting_key='write_lock'");
            $result=$fn(); $this->pdo->commit(); return $result;
        } catch (Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
}
