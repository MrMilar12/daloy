<?php
declare(strict_types=1);

/** Import the checked-in DepEd snapshot without replacing local location edits. */
function import_aurora_schools(Database $db): int {
    $snapshot=json_decode(file_get_contents(__DIR__.'/../database/aurora-schools.json'),true,512,JSON_THROW_ON_ERROR);
    return $db->transaction(function() use($db,$snapshot): int {
        $added=0;
        foreach($snapshot['schools'] as $school) {
            $key='aurora_school_'.implode('_',$school['school_ids']);
            if($db->one('SELECT setting_key FROM system_settings WHERE setting_key=?',[$key])) continue;
            $municipality=null;
            if($school['municipality']!=='') {
                $municipality=$db->one('SELECT id FROM municipalities WHERE name=?',[$school['municipality']])['id']??$db->insert('municipalities',['name'=>$school['municipality']]);
            }
            $address=trim($school['address']);
            if($address==='' || $address==='-') $address='Aurora, Philippines';
            $address.="\nDepEd school ID(s): ".implode(', ',$school['school_ids']);
            $existing=$db->one("SELECT id FROM locations WHERE type='School' AND name=? AND address=? ORDER BY id LIMIT 1",[$school['name'],$address]);
            $id=$existing['id']??null;
            if(!$id) {
                $id=$db->insert('locations',['name'=>$school['name'],'type'=>'School','municipality_id'=>$municipality,'address'=>$address,'required_nurses'=>0]);
                $added++;
            }
            $db->insert('system_settings',['setting_key'=>$key,'setting_value'=>(string)$id]);
        }
        return $added;
    });
}
