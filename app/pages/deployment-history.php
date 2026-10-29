<?php
page_heading('ASSIGNMENT RECORDS','Assignment history','Review confirmed deployments, acknowledgments, and retained assignment details.');

$historyWhere=$service->manager()?'1=1':'d.nurse_id='.(int)$user['id'];
$rows=$db->all("SELECT d.*,COALESCE(u.name,'Personnel record unavailable') nurse_name,COALESCE(a.title,'Activity record unavailable') title,COALESCE(l.name,a.destination_name,'Location unavailable') location_name,COALESCE(l.address,a.destination_address,'') location_address,COALESCE(h.name,'Administrator record unavailable') authorized_name FROM deployments d LEFT JOIN users u ON u.id=d.nurse_id LEFT JOIN users h ON h.id=d.authorized_by LEFT JOIN activities a ON a.id=d.activity_id LEFT JOIN locations l ON l.id=d.location_id WHERE $historyWhere ORDER BY d.created_at DESC,d.id DESC LIMIT 26 OFFSET $offset");
?>
<section class="card deployment-history">
    <div class="section-heading"><h2>Assignments & deployment history</h2><span class="muted">Original records are retained</span></div>
    <?php if(!$rows): empty_state('No deployments yet','Confirmed assignments and acknowledgments will appear here.'); else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Nurse / activity</th><th>Destination & time</th><th>Authorization</th><th>Acknowledgment</th><th>Details / action</th></tr></thead>
            <tbody><?php foreach(array_slice($rows,0,25) as $r): ?>
                <tr>
                    <td><strong><?=e($r['nurse_name'])?></strong><small><?=e($r['title'])?></small><small>Deployment #<?=$r['id']?></small></td>
                    <td><?=e($r['location_name'])?><?php if($r['location_address']): ?><small><?=e($r['location_address'])?></small><?php endif ?><?=range_label($r)?></td>
                    <td><?=e($r['authorized_name'])?><small><?=e(dt($r['created_at']))?></small></td>
                    <td><?=badge($r['status'])?><small><?=$r['acknowledged_at']?e(dt($r['acknowledged_at'])):($r['status']==='Cancelled'?'—':'Awaiting acknowledgment')?></small></td>
                    <td><details><summary>Assignment details</summary><dl class="detail-list"><dt>Original home assignment</dt><dd><?=e($r['original_assignment'])?></dd><dt>Reason</dt><dd><?=e($r['reason'])?></dd><dt>Instructions</dt><dd><?=nl2br(e($r['instructions']))?></dd><?php if($r['status']==='Cancelled'): ?><dt>Cancellation</dt><dd><?=e($r['cancellation_reason'])?> · <?=e(dt($r['cancelled_at']))?></dd><?php endif ?></dl></details>
                        <?php if(!$service->manager() && !$r['acknowledged_at'] && $r['status']!=='Cancelled') simple_action('acknowledge',(int)$r['id'],'Acknowledge assignment','?page=deployment-history'); if($service->manager()) cancel_control('deployments',$r,'?page=deployment-history'); ?>
                    </td>
                </tr>
            <?php endforeach ?></tbody>
        </table></div>
    <?php endif ?>
</section>
<?php pagination(count($rows),$pageNum);
