<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php'; require __DIR__.'/app/Auth.php'; require __DIR__.'/app/Reports.php'; start_session();
try {
    $db=new Database($config); $user=(new Auth($db))->user(); if(!$user || $user['must_change_password']) throw new DomainException('Sign in and activate your account first.',403);
    $service=new Service($db,$user); $type=(string)($_GET['type']??'daily'); $from=(string)($_GET['from']??date('Y-m-d')); $to=(string)($_GET['to']??$from);
    foreach([$from,$to] as $date) { $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date); if(!$parsed || $parsed->format('Y-m-d')!==$date) throw new DomainException('Invalid reporting date.',422); }
    $f=$_GET; $f['starts_at']=$from.' 00:00:00'; $f['ends_at']=date('Y-m-d',strtotime($to.' +1 day')).' 00:00:00';
    $report=Reports::build($service,$type,$f); $title='DALOY · '.Reports::TYPES[$type].' · '.$from.' to '.$to;
    $format=$_GET['format']??'xlsx';
    if($format==='xlsx') { $bytes=Reports::xlsx($report,$title); header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="daloy-'.$type.'-'.$from.'.xlsx"'); echo $bytes; }
    elseif($format==='pdf') { $bytes=Reports::pdf($report,$title); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="daloy-'.$type.'-'.$from.'.pdf"'); echo $bytes; }
    elseif($format==='print') { ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?></title><link rel="stylesheet" href="<?=e(asset_url('app.css'))?>"><script src="<?=e(asset_url('app.js'))?>" defer></script></head><body class="print-view"><button class="primary print-button" type="button">Print / save as PDF</button><h1><?=e($title)?></h1><p>Generated <?=e(now())?> · Asia/Manila · <?=count($report['rows'])?> records</p><table><thead><tr><?php foreach($report['columns'] as $c): ?><th><?=e($c)?></th><?php endforeach ?></tr></thead><tbody><?php foreach($report['rows'] as $row): ?><tr><?php foreach($row as $v): ?><td><?=e($v)?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table><?php if(!$report['rows']): ?><p>No records match the selected filters.</p><?php endif ?></body></html><?php }
    else throw new DomainException('Unknown export format.',422);
} catch(DomainException $e) { http_response_code($e->getCode()?:422); echo e($e->getMessage()); }
catch(Throwable $e) { error_log((string)$e); http_response_code(500); echo 'The report could not be generated.'; }
