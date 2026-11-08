<?php
/**
 * Shipping History card. Expects $e, $lang, $courier_track (oldest first),
 * $hist_count, $mode, $core. On air shipments a status that maps to a journey
 * stage is shown with the stage's customer-facing label.
 *
 * Newest first, as an ordered list (reversed) with <time> stamps. Beyond six
 * entries the older ones fold behind a button once JavaScript runs; without
 * JavaScript the whole history shows.
 */
require_once 'helpers/track_ui.php';

$histVisible = 6;
$histFold    = $hist_count > $histVisible + 2 ? $hist_count - $histVisible : 0;
?>
<section class="trk-card" data-reveal aria-labelledby="trk-history-title">
	<div class="trk-card__head">
		<span class="ico" aria-hidden="true"><i class="mdi mdi-history"></i></span>
		<h2 class="trk-card__title" id="trk-history-title"><?php echo $e($lang['track-shipment22'] ?? 'Shipping History'); ?></h2>
		<?php if ($hist_count > 0) : ?><span class="trk-card__count"><?php echo (int) $hist_count; ?> <?php echo $hist_count === 1 ? 'update' : 'updates'; ?></span><?php endif; ?>
	</div>
	<div class="trk-card__body">
		<?php if ($hist_count > 0) :
			$reversed = array_reverse($courier_track); ?>
			<ol class="trk-timeline<?php echo $histFold > 0 ? ' has-fold' : ''; ?>" id="trk-history" reversed aria-label="Tracking updates, newest first">
				<?php foreach ($reversed as $idx => $rows) :
					$loc   = trim(($rows->t_dest ?? '') . (!empty($rows->t_city) ? ', ' . $rows->t_city : ''));
					$label = $mode === 'air' ? cdp_airEventLabel($rows, $core->site_name) : (string) $rows->mod_style;
					$fl    = trim((string) ($rows->flight_no ?? ''));
					$aw    = trim((string) ($rows->awb_no ?? ''));
					$when  = (string) ($rows->t_date ?? '');
					$early = $histFold > 0 && $idx >= $histVisible; ?>
					<li class="trk-tl <?php echo $idx === 0 ? 'is-latest' : ''; ?><?php echo $early ? ' trk-tl--early' : ''; ?>">
						<span class="trk-tl__dot" aria-hidden="true"></span>
						<time class="trk-tl__date" datetime="<?php echo $e(cdp_trackIsoDate($when)); ?>">
							<?php echo $e(cdp_trackFmtDate($when)); ?>
							<?php if ($idx === 0) : ?><span class="trk-tl__rel">· <?php echo $e(cdp_trackRelativeDate($when)); ?></span><?php endif; ?>
						</time>
						<div class="trk-tl__status"><?php echo $e($label); ?><?php if ($idx === 0) : ?> <span class="trk-sr">(latest)</span><?php endif; ?></div>
						<?php if ($loc) : ?><div class="trk-tl__loc"><i class="mdi mdi-map-marker-outline" aria-hidden="true"></i> <?php echo $e($loc); ?></div><?php endif; ?>
						<?php if ($fl || $aw) : ?>
							<div class="trk-tl__loc">
								<?php if ($fl) : ?><i class="mdi mdi-airplane" aria-hidden="true"></i> Flight <?php echo $e($fl); ?><?php endif; ?>
								<?php if ($fl && $aw) : ?> · <?php endif; ?>
								<?php if ($aw) : ?>AWB <?php echo $e($aw); ?><?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if (!empty($rows->comments)) : ?><div class="trk-tl__note"><?php echo $e($rows->comments); ?></div><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
			<?php if ($histFold > 0) : ?>
				<button type="button" class="trk-fold__btn trk-fold__btn--history" data-fold-for="trk-history" aria-expanded="false" aria-controls="trk-history" hidden>
					<i class="mdi mdi-chevron-down" aria-hidden="true"></i>
					<span class="trk-fold__show">Show <?php echo (int) $histFold; ?> earlier <?php echo $histFold === 1 ? 'update' : 'updates'; ?></span>
					<span class="trk-fold__hide">Show fewer</span>
				</button>
			<?php endif; ?>
		<?php else : ?>
			<div class="trk-note">
				<span class="trk-note__ico" aria-hidden="true"><i class="mdi mdi-timer-sand"></i></span>
				<p>No tracking updates have been logged for this shipment yet.</p>
				<p class="trk-note__sub">Updates appear here as the shipment moves through each stage.</p>
			</div>
		<?php endif; ?>
	</div>
</section>
