<?php
require_once CDP_ROOT.'/helpers/delivery_service.php';
if(sl_tableExists('cdb_delivery_requests')):
$dl=new DeliveryService();$jobs=$dl->sql("SELECT r.* FROM cdb_delivery_requests r WHERE r.driver_id=? AND (r.state IN ('assigned','onroute','failed') OR (r.state='delivered' AND r.completed_at>=CURDATE())) ORDER BY FIELD(r.state,'onroute','assigned','failed','delivered'),r.approved_at,r.id",[(int)$_SESSION['userid']])->fetchAll(PDO::FETCH_OBJ);
$config=dl_config();$open=array_values(array_filter($jobs,fn($r)=>in_array($r->state,['assigned','onroute'],true)));
if (($tab??'')==='pay') {
    $earnings=[];
    foreach($jobs as $job)if($job->state==='delivered')$earnings[$job->currency]=($earnings[$job->currency]??0)+(int)$job->rider_cents;
    ?><section class="da-sheet"><h2 class="sl-ct">Customer delivery share earned today</h2><?php if(!$earnings): ?><p class="mut">No completed customer deliveries today.</p><?php endif; foreach($earnings as $currency=>$amount): ?><b class="sl-title"><?= h(dl_money($amount,$currency)) ?></b><?php endforeach; ?><p class="sm mut">Approved rider shares for completed customer deliveries; settlement is handled separately from other assigned jobs.</p></section><?php
    return;
}
?>
<section class="da-sheet" style="order:-3"><div class="row between wrap g8"><h2 class="sl-ct">Customer deliveries</h2><span class="sl-badge"><?= count($jobs) ?> requests</span></div>
<?php if(!$jobs): ?><p class="mut sm">Customer-approved air and sea deliveries appear here when dispatch assigns them to you.</p><?php endif; ?>
<?php if($open): ?><div id="dl-rider-map" style="height:300px;border-radius:16px;background:var(--field)" role="region" aria-label="Assigned delivery stops map"></div><p id="dl-rider-map-error" class="mut sm" hidden>Map unavailable. Use Navigate on a stop to open Google Maps.</p>
<div id="dl-rider-map-config" data-browser-key="<?= h(getenv('CDP_GOOGLE_MAPS_BROWSER_KEY')?:$config['browser_key']) ?>" data-stops="<?= h(json_encode(array_map(fn($r)=>['id'=>(int)$r->id,'lat'=>(float)$r->lat,'lng'=>(float)$r->lng,'address'=>$r->address],array_slice($open,0,100)))) ?>"></div><?php endif; ?>
<?php foreach($jobs as $i=>$job): ?><div class="col g8" style="padding:12px 0;border-top:1px solid var(--line)"><a class="row g8" href="delivery_job.php?id=<?= (int)$job->id ?>"><span class="da-n"><?= $i+1 ?></span><span class="col grow" style="min-width:0"><b><?= h($job->contact_name) ?></b><span class="sm"><?= h($job->address) ?></span><small class="mut"><?= count(json_decode($job->parcel_json,true)) ?> <?= h($job->module) ?> parcels · <?= h($job->state) ?></small></span></a><div class="row wrap g8"><a class="da-sec" href="delivery_job.php?id=<?= (int)$job->id ?>">Open job</a><?php if(in_array($job->state,['assigned','onroute'],true)): ?><a class="da-sec" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= h(rawurlencode($job->lat.','.$job->lng)) ?>&amp;destination_place_id=<?= h(rawurlencode($job->place_id)) ?>&amp;travelmode=driving" target="_blank" rel="noopener">Navigate</a><?php endif; ?></div><span class="mut sm">Fee <?= h(dl_money((int)$job->amount_cents,$job->currency)) ?> · Your share <?= h(dl_money((int)$job->rider_cents,$job->currency)) ?></span></div><?php endforeach; ?></section>
<script defer src="<?= h(cdp_asset('dataJs/sl/delivery_rider_map.js')) ?>"></script>
<?php endif; ?>
