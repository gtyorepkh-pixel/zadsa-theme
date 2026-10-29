<?php
/**
 * The template for displaying search form.
 *
 * @package ZadPro
 */
defined( 'ABSPATH' ) || exit; ?>

<form role="search" method="get" class="search-form" action="<?= esc_url(home_url('/')); ?>" accept-charset="UTF-8">
            <label for="searchfield"><span class="screen-reader">ابحث</span></label>
            <input id="searchfield" type="search" class="search-field" placeholder="بحث" name="s" />
                <button type="submit" class="search-submit"><?php echo zad_search_icon(); // phpcs:ignore ?></button>
        </form>