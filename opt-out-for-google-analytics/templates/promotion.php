<?php
    // If this file is called directly, abort.
    defined( 'WPINC' ) || die;

    // The template is included in a method, so the variables are local and not global.
    // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div class="gaoo-promotion-wrap">

<?php foreach ( $promo as $item ): ?>

    <?php
    if ( GAOO_Promo::should_hide( isset( $item[ 'hide_if_active' ] ) ? $item[ 'hide_if_active' ] : array() ) ):
        continue;
    endif;
    ?>

    <div class="gaoo-promotion-box">

        <div class="gaoo-promotion-box-body">

            <?php if ( ! empty( $item[ 'link' ] ) ): ?>
            <a href="<?php echo esc_url( $item[ 'link' ] ); ?>" target="_blank" class="gaoo-promotion-link" title="<?php esc_attr_e( 'Opens in new tab', 'opt-out-for-google-analytics' ); ?>">
                <?php endif; ?>

                <?php if ( ! empty( $item[ 'image' ] ) ): ?>
                    <img src="<?php echo esc_url( $item[ 'image' ] ); ?>" alt="<?php echo empty( $item[ 'title' ] ) ? '' : esc_attr( $item[ 'title' ] ); ?>">
                <?php endif; ?>

                <?php if ( ! empty( $item[ 'link' ] ) ): ?>
            </a>
        <?php endif; ?>

            <?php if ( ! empty( $item[ 'desc' ] ) ): ?>
                <div class="gaoo-promotion-description">
                    <?php echo wp_kses_post( $item[ 'desc' ] ); ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

<?php endforeach; ?>

</div>