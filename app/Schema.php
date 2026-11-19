<?php
declare(strict_types=1);

function ensure_memo_schema(Database $db): void {
    $tables=array_column($db->all("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('workspace_memos','memo_acknowledgments')"),'TABLE_NAME');
    if(count($tables)<2) {
        $schema=file_get_contents(ROOT.'/database/schema.sql');
        foreach(explode(';',$schema) as $statement) {
            if(preg_match('/^\s*CREATE TABLE IF NOT EXISTS (workspace_memos|memo_acknowledgments)\s*\(/',$statement)) $db->pdo->exec(trim($statement));
        }
    }
    if(!$db->all("SHOW COLUMNS FROM workspace_memos LIKE 'attachment'")) {
        try { $db->pdo->exec('ALTER TABLE workspace_memos ADD COLUMN attachment VARCHAR(255) NULL'); }
        catch(PDOException $error) { if(($error->errorInfo[1]??null)!==1060) throw $error; }
    }
    if($db->one('SELECT id FROM workspace_memos LIMIT 1')) return;
    $notice=$db->one("SELECT setting_value FROM system_settings WHERE setting_key='system_notice'")['setting_value']??'';
    if(trim($notice)!=='') {
        $db->run("INSERT INTO workspace_memos (id,title,body,published_at) VALUES (1,'Workspace notice',?,?)
            ON DUPLICATE KEY UPDATE id=id",[$notice,now()]);
    }
}

/** Upgrade existing MySQL installations for free-text activity destinations. */
function ensure_custom_destination_schema(Database $db): void {
    if($db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') return;
    foreach(['activities','deployments'] as $table) {
        $columns=$db->all('SELECT COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        if(!$columns) continue;
        $names=array_column($columns,'COLUMN_NAME');
        if($table==='activities') {
            if(!in_array('destination_name',$names,true)) $db->pdo->exec('ALTER TABLE activities ADD COLUMN destination_name VARCHAR(190) NULL AFTER location_id');
            if(!in_array('destination_address',$names,true)) $db->pdo->exec('ALTER TABLE activities ADD COLUMN destination_address TEXT NULL AFTER destination_name');
        }
        $locationColumn=array_values(array_filter($columns,fn($column)=>$column['COLUMN_NAME']==='location_id'))[0]??null;
        if($locationColumn && $locationColumn['IS_NULLABLE']==='NO') $db->pdo->exec("ALTER TABLE $table MODIFY location_id INTEGER NULL");
    }
    $requestColumns=$db->all("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='registration_requests'");
    if($requestColumns && !in_array('requested_role',array_column($requestColumns,'COLUMN_NAME'),true)) {
        $db->pdo->exec("ALTER TABLE registration_requests ADD COLUMN requested_role VARCHAR(20) NOT NULL DEFAULT 'nurse' AFTER password_hash");
    }
}
