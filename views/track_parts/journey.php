<?php
/**
 * Shipment Journey card — shared by views/track.php and
 * views/track_online_shopping.php. Expects in scope:
 *   $e (escaper), $progress (cdp_trackProgress / cdp_airJourney result),
 *   $status_name, $mode ('air'|'sea'), $current_step.
 *
 * Every step carries label / icon / desc; air steps also carry at / flight_no.
 * Copy rules: state facts about the shipment, never instruct the customer.
 *
 * The rail is an ordered list: the current step is aria-current="step", each
 * marker has a text equivalent, and timestamps are <time> elements. On a long
 * rail (air, 16 stages) the completed stages before the last two fold away
 * behind a button once JavaScript runs; without JavaScript every stage shows.
 */
require_once 'helpers/track_ui.php';

$summary   = cdp_trackStageSummary($progress);
$foldCount = cdp_trackFoldCount($progress);
$railId    = 'trk-journey-' . ($mode === 'air' ? 'air' : 'sea');
$stageNow  = $progress['steps'][$progress['index']] ?? null;
$stageAt   = !empty($stageNow['at']) ? $stageNow['at'] : null;
?>
<section class="trk-card" data-reveal aria-labelledby="<?php echo $railId; ?>-title">
	<div class="trk-card__head">
		<span class="ico" aria-hidden="true"><i class="mdi mdi-transit-connection-variant"></i></span>
		<h2 class="trk-card__title" id="<?php echo $railId; ?>-title">Shipment Journey</h2>
		<?php if ($mode === 'air') : ?>
			<span class="trk-card__hint"><?php echo $e($progress['route']['origin']['code']); ?> → <?php echo $e($progress['route']['transit']['code']); ?> → <?php echo $e($progress['route']['destination']['code']); ?></span>
		<?php endif; ?>
	</div>

	<!-- Where the shipment is, in one line, before the detail -->
	<div class="trk-progress">
		<div class="trk-progress__row">
			<div class="trk-progress__now">
				<span class="trk-progress__eyebrow">Stage <?php echo (int) $summary['current']; ?> of <?php echo (int) $summary['count']; ?></span>
				<span class="trk-progress__label"><?php echo $e($summary['label'] ?: $status_name); ?></span>
			</div>
			<div class="trk-progress__pct"><span data-countup="<?php echo (int) $summary['percent']; ?>"><?php echo (int) $summary['percent']; ?></span>%<small>of journey</small></div>
		</div>
		<div class="trk-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo (int) $summary['count']; ?>" aria-valuenow="<?php echo (int) $summary['current']; ?>" aria-valuetext="Stage <?php echo (int) $summary['current']; ?> of <?php echo (int) $summary['count']; ?>: <?php echo $e($summary['label']); ?>">
			<span class="trk-progress__fill" style="--p: <?php echo (int) $summary['percent']; ?>%;"></span>
		</div>
		<?php if ($stageAt || $summary['next'] !== null) : ?>
		<div class="trk-progress__meta">
			<?php if ($stageAt) : ?>
				<span><i class="mdi mdi-clock-outline" aria-hidden="true"></i> Reached <time datetime="<?php echo $e(cdp_trackIsoDate($stageAt)); ?>" title="<?php echo $e(cdp_trackFmtDate($stageAt)); ?>"><?php echo $e(cdp_trackRelativeDate($stageAt)); ?></time></span>
			<?php endif; ?>
			<?php if ($summary['next'] !== null) : ?>
				<span><i class="mdi mdi-arrow-right-thin" aria-hidden="true"></i> Next: <b><?php echo $e($summary['next']); ?></b></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>

	<ol class="trk-journey <?php echo $mode === 'air' ? 'trk-journey--air' : ''; ?><?php echo $foldCount > 0 ? ' has-fold' : ''; ?>" id="<?php echo $railId; ?>" aria-label="Journey stages">
		<?php if ($foldCount > 0) : ?>
		<li class="trk-fold" data-fold-for="<?php echo $railId; ?>">
			<button type="button" class="trk-fold__btn" aria-expanded="false" aria-controls="<?php echo $railId; ?>" hidden>
				<i class="mdi mdi-chevron-down" aria-hidden="true"></i>
				<span class="trk-fold__show">Show <?php echo (int) $foldCount; ?> earlier <?php echo $foldCount === 1 ? 'stage' : 'stages'; ?></span>
				<span class="trk-fold__hide">Hide earlier stages</span>
			</button>
		</li>
		<?php endif; ?>

		<?php foreach ($progress['steps'] as $i => $s) :
			$isDone    = $i < $progress['index'];
			$isCurrent = $i === $progress['index'];
			$cls   = $isDone ? 'is-done' : ($isCurrent ? 'is-current' : 'is-next');
			if ($i < $foldCount) { $cls .= ' trk-stage--early'; }
			$state = $isDone ? 'Completed' : ($isCurrent ? 'Current stage' : 'Upcoming');
			$at    = !empty($s['at']) ? (string) $s['at'] : '';
			$fl    = trim((string) ($s['flight_no'] ?? '')); ?>
			<li class="trk-stage <?php echo $cls; ?>"<?php echo $isCurrent ? ' aria-current="step"' : ''; ?>>
				<div class="trk-stage__dot" aria-hidden="true"><?php echo $isDone ? '<i class="mdi mdi-check"></i>' : $s['icon']; ?></div>
				<div class="trk-stage__body">
					<div class="trk-stage__label">
						<span class="trk-stage__num"><?php echo (int) $i + 1; ?>.</span>
						<?php echo $e($s['label']); ?>
						<?php if ($isCurrent) : ?><span class="trk-now">Current Stage</span><?php else : ?><span class="trk-sr"><?php echo $state; ?></span><?php endif; ?>
					</div>
					<?php if (!empty($s['desc'])) : ?>
						<div class="trk-stage__desc"><?php echo $e($s['desc']); ?></div>
					<?php endif; ?>
					<?php if ($at || $fl) : ?>
						<div class="trk-stage__meta">
							<?php if ($at) : ?>
								<time class="trk-stage__time" datetime="<?php echo $e(cdp_trackIsoDate($at)); ?>"><i class="mdi mdi-clock-outline" aria-hidden="true"></i> <?php echo $e(cdp_trackFmtDate($at)); ?><?php if ($isCurrent) : ?> <span class="trk-stage__rel">· <?php echo $e(cdp_trackRelativeDate($at)); ?></span><?php endif; ?></time>
							<?php endif; ?>
							<?php if ($fl) : ?><span class="trk-stage__flight"><i class="mdi mdi-airplane" aria-hidden="true"></i> Flight <?php echo $e($fl); ?></span><?php endif; ?>
						</div>
					<?php elseif ($mode === 'air' && $i <= $progress['index']) : ?>
						<div class="trk-stage__meta"><span class="trk-stage__time is-missing">Date and time not recorded</span></div>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>

	<div class="trk-journey__foot">
		<?php if ($summary['done']) : ?>
			This shipment has reached the final stage of its journey.
		<?php else : ?>
			This shipment is at <b><?php echo $e($summary['label'] ?: $status_name); ?></b>, stage <?php echo (int) $summary['current']; ?> of <?php echo (int) $summary['count']; ?>.
			The stages above update as it moves.
		<?php endif; ?>
	</div>
</section>
