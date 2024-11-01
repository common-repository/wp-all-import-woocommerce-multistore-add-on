<?php
/**
 * Product functionality.
 *
 * This handles product related functionality.
 *
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WPAI_WC_Multistore_Product
 */
class WPAI_WC_Multistore_Product{

	public function __construct(){
		$this->hooks();
	}

	public function hooks(){
		add_action( 'pmwi_tab_header', array( $this, 'tab_header' ) );
		add_action( 'pmwi_tab_content', array( $this, 'tab_content' ) );
		add_filter( 'pmxi_options_options', array( $this, 'pmxi_options_options' ) );

		if( ! $this->is_importing() ){
			return;
		}

		add_action( 'pmxi_saved_post', array( $this, 'pmxi_saved_post' ), 10 );
		add_action( 'wp_all_import_make_product_simple', array( $this, 'wp_all_import_make_product_simple' ), 10 );
	}

	/**
	 *
	 */
	public function tab_header() {
		printf(
			'<li class="woonet_tab"><a href="#woonet_data" rel="woonet_data"><span>%s</span></a></li>',
			__( 'MultiStore', 'woonet' )
		);
	}

	/**
	 *
	 */
	public function tab_content() {
		include( WPAI_WC_MULTISTORE_ABSPATH. '/views/admin/import/product/_tabs/_wc-multistore.php' );
	}


	/**
	 * @param $options
	 *
	 * @return mixed
	 */
	public function pmxi_options_options( $options ) {
		if ( ! isset( $_POST['is_submitted'] ) ) {
			return $options;
		}

		$options['_woonet_global_publish_to'] = $_POST['_woonet_global_publish_to'];
		$options['_woonet_global_inherit'] = $_POST['_woonet_global_inherit'];
		$options['_woonet_global_stock'] = $_POST['_woonet_global_stock'];

		foreach (WOO_MULTISTORE()->active_sites as $site) {
			$options['_woonet_publish_to_'.$site->get_id()] = $_POST['_woonet_publish_to_'.$site->get_id()];
			$options['_woonet_publish_to_'.$site->get_id().'_child_inheir'] = $_POST['_woonet_publish_to_'.$site->get_id().'_child_inheir'];
			$options['_woonet_'.$site->get_id().'_child_stock_synchronize'] = $_POST['_woonet_'.$site->get_id().'_child_stock_synchronize'];
		}

		return $options;
	}

	/**
	 * @param $product_id
	 */
	public function wp_all_import_make_product_simple( $product_id ) {
		$wc_product = wc_get_product($product_id);
		$classname = wc_multistore_get_product_class_name( 'master', $wc_product->get_type() );

		if( ! $classname ){
			return;
		}

		$wc_multistore_product_master = new $classname( $wc_product );

		if( $wc_multistore_product_master->is_enabled_sync ){
			$wc_multistore_product_master->set_scheduler( 'wc_multistore_scheduled_products' );
		}
	}

	/**
	 * @param $product_id
	 */
	public function pmxi_saved_post( $product_id ) {
		if ( $wc_product = wc_get_product( $product_id ) ) {
			if( $wc_product->get_type() == 'variation' ){
				return;
			}

			$options = $this->get_options();
			$classname = wc_multistore_get_product_class_name( 'master', $wc_product->get_type() );

			if( ! $classname ){
				return;
			}

			$wc_multistore_product_master = new $classname( $wc_product );

			foreach (WOO_MULTISTORE()->active_sites as $site ){
				if( $options['_woonet_publish_to_' . $site->get_id()] == 'yes' ){
					$wc_multistore_product_master->is_enabled_sync = true;
				}

				if( ! empty( $options['_woonet_publish_to_' . $site->get_id()] ) ){
					$wc_multistore_product_master->settings['_woonet_publish_to_' . $site->get_id()] = $options['_woonet_publish_to_' . $site->get_id()];
				}

				if( ! empty( $options['_woonet_publish_to_' . $site->get_id() . '_child_inheir'] ) ){
					$wc_multistore_product_master->settings['_woonet_publish_to_' . $site->get_id() . '_child_inheir' ] = $options['_woonet_publish_to_' . $site->get_id() . '_child_inheir'];
				}

				if( ! empty( $options['_woonet_' . $site->get_id() . '_child_stock_synchronize'] ) ){
					$wc_multistore_product_master->settings['_woonet_' . $site->get_id() . '_child_stock_synchronize' ] = $options['_woonet_' . $site->get_id() . '_child_stock_synchronize'];
				}
			}

			$wc_multistore_product_master->save_settings();
			$wc_multistore_product_master->save();

			if( $wc_multistore_product_master->is_enabled_sync ){
				$wc_multistore_product_master->set_scheduler( 'wc_multistore_scheduled_products' );
			}
		}
	}

	/**
	 * Get import options from session
	 */
	private function get_options() {
		if ( ! empty( PMXI_Plugin::$session ) ) {
			$options = PMXI_Plugin::$session->options; // not available when running via cron.
		} else {
			$import  = new PMXI_Import_Record();
			$options = $import->getById( $_GET['import_id'] );
			$options = $options->options;
		}

		return $options;
	}

	/**
	 * @return bool
	 */
	public function is_importing(){
		// Importing from dashboard
		if( ! empty($_REQUEST['page'] ) && $_REQUEST['page'] == 'pmxi-admin-import' && ! empty($_REQUEST['action'] ) && $_REQUEST['action'] == 'process' ) {
			return true;
		}
		// Importing by scheduler
		if( ! empty($_REQUEST['import_key']) ){
			return true;
		}

		return false;
	}
}