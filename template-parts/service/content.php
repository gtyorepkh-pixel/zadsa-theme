<?php defined( 'ABSPATH' ) || exit; ?>

	<!-- 3. Intro (content) -->
	<section class="sec">
		<div class="wrap slayout slayout--solo">
			<div class="slayout__main">
				<?php $ctitle = trim( (string) $m( 'content_title' ) ); if ( '' !== $ctitle ) : // optional H2 only when filled; never the page title (it is already the H1) ?>
				<h2 class="h-line"><?php echo esc_html( $ctitle ); ?></h2>
				<?php endif; ?>
				<?php
				ob_start();
				the_content();
				list( $ix_before, $ix_after ) = zad_ix_split( ob_get_clean(), $id );
				?>
				<div class="prose entry-content"><?php echo $ix_before; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<p><a class="btn btn--accent" href="#quote" data-scroll-quote><?php echo zad_icon( 'bolt', 20 ); // phpcs:ignore ?> <?php echo esc_html( $cta ); ?></a></p>
			</div>
		</div>
	</section>
