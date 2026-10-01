<?php
/**
 * The template for displaying search form.
 *
 * @package memohost
 */
defined( 'ABSPATH' ) || exit; ?>

<form role="search" method="get" class="search-form" action="<?= esc_url(home_url('/')); ?>" accept-charset="UTF-8">
            <label for="searchfield"><span class="screen-reader">ابحث</span></label>
            <input id="searchfield" type="search" class="search-field" placeholder="بحث" name="s" />
                <button type="submit" class="search-submit"><?=  memo_get_svg( array( 'icon' => 'search','width' => '15','height' => '35','fill' => '#000', 'viewbox' => '0 0 24 24' ) ); ?></button>
        </form>