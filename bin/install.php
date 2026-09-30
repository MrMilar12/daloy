<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$options=getopt('',['demo','email:','name:']);
try {
    if(str_starts_with($config['dsn'],'mysql:')) {
        if(!preg_match('/dbname=([a-zA-Z0-9_]+)/',$config['dsn'],$match)) throw new RuntimeException('Invalid database name.');
        $server=new PDO(preg_replace('/;?dbname=[^;]+/','',$config['dsn']),$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $server->exec('CREATE DATABASE IF NOT EXISTS `'.$match[1].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    $db=new Database($config);
    $sql=file_get_contents(ROOT.'/database/schema.sql');
    foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') $db->pdo->exec($statement);
    if($db->one('SELECT id FROM users LIMIT 1')) { echo "DALOY is already installed. No data was changed.\n"; exit; }
    $db->transaction(function() use($db,$options) {
        foreach(['write_lock'=>'1','organization'=>'Schools Division Office of Aurora','system_notice'=>''] as $k=>$v) $db->insert('system_settings',['setting_key'=>$k,'setting_value'=>$v]);
        foreach(['Baler','Casiguran','Dilasag','Dinalungan','Dingalan','Dipaculao','Maria Aurora','San Luis'] as $name) $db->insert('municipalities',['name'=>$name]);
        foreach(['Regular School Health Duty','School Visit','Division Activity','District Activity','Sports Event','Training / Seminar','Medical Assistance','Health Program','Monitoring','Emergency Response','Official Travel / Field Duty','Special Assignment','Other Official Duty'] as $name) $db->insert('duty_types',['name'=>$name]);
        foreach(['School Health Activity','Division Activity','District Activity','Sports Event','Training / Seminar','Medical Assistance','Emergency Response','School Visit','Health Monitoring','Temporary Staffing Requirement','Special Program','Official Duty','Other'] as $name) $db->insert('deployment_reasons',['name'=>$name]);
        $db->insert('shifts',['name'=>'Standard day','start_time'=>'08:00','end_time'=>'17:00']);
        $password=getenv('DALOY_ADMIN_PASSWORD')?:bin2hex(random_bytes(10));
        if(strlen($password)<12 || strlen($password)>72) throw new RuntimeException('DALOY_ADMIN_PASSWORD must be 12–72 bytes.');
        $email=$options['email']??'admin@daloy.local'; if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid administrator email.');
        $id=$db->insert('users',['name'=>$options['name']??'DALOY Administrator','email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'admin','designation'=>'System Administrator','created_at'=>now()]);
        echo "Administrator: $email\nTemporary password: $password\nChange this password at first sign-in.\n";
        if(isset($options['demo'])) {
            $db->run("UPDATE system_settings SET setting_value=? WHERE setting_key='system_notice'",['Demonstration workspace — all personnel, schools, and activities marked Demo are fictional.']);
            $district=$db->insert('districts',['name'=>'Demo Central District']);
            $locations=[]; foreach(['Demo Central School','Demo Coastal School','Demo Division Office','Demo Sports Grounds'] as $i=>$name) $locations[]=$db->insert('locations',['name'=>$name,'type'=>$i<2?'School':($i===2?'SDO Office':'Sports Venue'),'district_id'=>$district,'municipality_id'=>1,'required_nurses'=>$i<2?1:0]);
            $demoPassword=bin2hex(random_bytes(10));
            $head=$db->insert('users',['name'=>'Demo Nurse Supervisor','email'=>'head@daloy.local','password_hash'=>password_hash($demoPassword,PASSWORD_DEFAULT),'role'=>'head','designation'=>'Nurse Head','created_at'=>now()]);
            $nurses=[];
            foreach(['Maria Santos','Anna Reyes','Jose Cruz','Sofia Garcia','Miguel Ramos','Isabel Flores','Paolo Mendoza','Camille Torres'] as $i=>$name) $nurses[]=$db->insert('users',['name'=>'Demo '.$name,'email'=>'nurse'.($i+1).'@daloy.local','password_hash'=>password_hash($demoPassword,PASSWORD_DEFAULT),'role'=>'nurse','employee_number'=>'DEMO-'.str_pad((string)($i+1),3,'0',STR_PAD_LEFT),'district_id'=>$district,'location_id'=>$locations[$i%3],'specialization'=>'School health','skills'=>'First aid, Basic life support','created_at'=>now()]);
            foreach([$nurses[0],$nurses[1],$nurses[2],$nurses[3]] as $i=>$nurse) $db->insert('schedules',['nurse_id'=>$nurse,'location_id'=>$locations[$i%3],'duty_type_id'=>1,'starts_at'=>date('Y-m-d').' 08:00:00','ends_at'=>date('Y-m-d').' 17:00:00','remarks'=>'Fictional demonstration duty','created_by'=>$head,'created_at'=>now(),'updated_at'=>now()]);
            $db->insert('activities',['title'=>'Demo school health outreach','location_id'=>$locations[0],'starts_at'=>date('Y-m-d',strtotime('+1 day')).' 08:00:00','ends_at'=>date('Y-m-d',strtotime('+1 day')).' 17:00:00','organizer'=>'Demo SDO health team','required_nurses'=>2,'required_skill'=>'First aid','reason_id'=>1,'instructions'=>'Demonstration activity. Bring the school health kit and report to the coordinator.','created_by'=>$head,'created_at'=>now()]);
            echo "Demo supervisor: head@daloy.local\nDemo nurses: nurse1@daloy.local through nurse8@daloy.local\nDemo temporary password: $demoPassword\n";
        }
    });
    require ROOT.'/app/AuroraSchools.php';
    $schools=import_aurora_schools($db);
    echo "Imported $schools Aurora school locations.\n";
    echo "Installation complete. Open http://localhost/daloy/\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
