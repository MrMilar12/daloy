<?php
declare(strict_types=1);

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
}
