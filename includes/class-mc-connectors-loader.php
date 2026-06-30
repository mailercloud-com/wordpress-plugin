<?php
/**
 * Mc_Connectors_Loader — instantiates the form-plugin connectors, registers the
 * hooks of the ones whose host plugin is active, and exposes the full list for
 * the Integrations admin page (so it can show active vs not-installed).
 *
 * @package Mailercloud
 */

if (! defined('ABSPATH')) {
    exit;
}

class Mc_Connectors_Loader
{
    /** @var Mc_Connector_Base[] */
    private $connectors = array();

    public function __construct()
    {
        $dir = plugin_dir_path(__FILE__) . 'connectors/';
        require_once $dir . 'class-mc-connector-base.php';
        foreach (array('cf7', 'wpforms', 'elementor', 'gravity', 'ninja', 'formidable') as $slug) {
            require_once $dir . 'class-mc-connector-' . $slug . '.php';
        }

        $this->connectors = array(
            new Mc_Connector_CF7(),
            new Mc_Connector_WPForms(),
            new Mc_Connector_Elementor(),
            new Mc_Connector_Gravity(),
            new Mc_Connector_Ninja(),
            new Mc_Connector_Formidable(),
        );
    }

    /**
     * Register submit hooks for every connector whose host plugin is active.
     * Connectors self-gate on their own enabled/config inside capture().
     */
    public function register()
    {
        foreach ($this->connectors as $connector) {
            if ($connector->is_active()) {
                $connector->register_hooks();
            }
        }
    }

    /** @return Mc_Connector_Base[] All connectors (active or not). */
    public function all()
    {
        return $this->connectors;
    }
}
