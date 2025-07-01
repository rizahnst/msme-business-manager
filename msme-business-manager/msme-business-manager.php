<?php
/**
 * Plugin Name: MSME Business Manager
 * Plugin URI: https://cobalah.id
 * Description: Sistem manajemen bisnis untuk UMKM Indonesia dengan multisite WordPress. Menyediakan landing page gratis untuk bisnis lokal dengan fitur review, galeri, integrasi marketplace, dan sistem iklan.
 * Version: 1.0.0
 * Author: MSME Development Team
 * Author URI: https://cobalah.id
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: msme-business-manager
 * Domain Path: /languages
 * Network: true
 * Requires at least: 6.4
 * Tested up to: 6.4
 * Requires PHP: 8.2
 *
 * @package MSME_Business_Manager
 * @version 1.0.0
 * @author MSME Development Team
 * @copyright 2025 MSME Business Manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('MSME_PLUGIN_VERSION', '1.0.0');
define('MSME_PLUGIN_FILE', __FILE__);
define('MSME_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MSME_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MSME_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main MSME Business Manager Class
 */
class MSME_Business_Manager {
    
    /**
     * Plugin instance
     * @var MSME_Business_Manager
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
        
        // Plugin initialization
        add_action('plugins_loaded', array($this, 'init_plugin'));
        add_action('wp_ajax_test_smtp_email', array($this, 'test_smtp_email'));
        $this->init_registration_system();
        
        $this->init_login_redirect();
        $this->configure_gmail_smtp();
        
        // Admin hooks
        add_action('network_admin_menu', array($this, 'add_network_admin_menu'));
        add_action('admin_menu', array($this, 'add_site_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX hooks (for future use)
        add_action('wp_ajax_msme_test_connection', array($this, 'test_database_connection'));
        add_action('wp_ajax_msme_test_connection', array($this, 'test_database_connection'));
        add_action('wp_ajax_check_subdomain_availability', array($this, 'check_subdomain_availability'));
        add_action('wp_ajax_nopriv_check_subdomain_availability', array($this, 'check_subdomain_availability'));
        add_action('wp_ajax_submit_business_registration', array($this, 'submit_business_registration'));
        add_action('wp_ajax_nopriv_submit_business_registration', array($this, 'submit_business_registration'));
        add_action('wp_ajax_verify_otp_code', array($this, 'verify_otp_code'));
        add_action('wp_ajax_nopriv_verify_otp_code', array($this, 'verify_otp_code'));
        
        // Schedule daily cleanup of expired registrations
        if (!wp_next_scheduled('msme_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'msme_daily_cleanup');
        }
        add_action('msme_daily_cleanup', array($this, 'cleanup_expired_registrations'));
        add_action('wp_ajax_manual_cleanup_registrations', array($this, 'manual_cleanup_registrations'));
        add_action('wp_ajax_resend_otp_code', array($this, 'resend_otp_code'));
        add_action('wp_ajax_nopriv_resend_otp_code', array($this, 'resend_otp_code'));
        add_action('wp_ajax_resend_otp_code', array($this, 'resend_otp_code'));
        add_action('wp_ajax_nopriv_resend_otp_code', array($this, 'resend_otp_code'));
    }
    
    /**
     * Plugin activation
     */
    public function activate_plugin() {
        global $wpdb;
        
        // Start transaction for rollback capability
        $wpdb->query('START TRANSACTION');
        
        try {
            // Check WordPress and PHP version requirements
            $this->check_requirements();
            
            // Create all database tables
            $this->create_database_tables();
            
            // Insert initial data
            $this->insert_initial_data();
            
            // Set plugin version and activation date
            $this->set_plugin_options();
            
            // Create upload directories
            $this->create_upload_directories();
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Log successful activation
            error_log('MSME Business Manager: Plugin activated successfully');
            
            // Set activation notice
            set_transient('msme_activation_notice', true, 30);
            
            update_option('msme_flush_rewrite_rules', true);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            
            // Log error
            error_log('MSME Business Manager activation error: ' . $e->getMessage());
            
            // Display error message
            wp_die(
                'MSME Business Manager activation failed: ' . $e->getMessage(),
                'Plugin Activation Error',
                array('back_link' => true)
            );
        }
    }
    
    /**
     * Check system requirements
     */
    private function check_requirements() {
        global $wp_version;
        
        // Check WordPress version
        if (version_compare($wp_version, '6.4', '<')) {
            throw new Exception('WordPress 6.4 or higher is required.');
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.2', '<')) {
            throw new Exception('PHP 8.2 or higher is required.');
        }
        
        // Check if multisite is enabled
        if (!is_multisite()) {
            throw new Exception('WordPress Multisite is required.');
        }
        
        // Check required PHP extensions
        $required_extensions = array('mysqli', 'gd', 'curl', 'json');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new Exception("PHP extension '{$extension}' is required.");
            }
        }
    }
    
    /**
     * Create all database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        // Get table prefix for multisite
        $table_prefix = $wpdb->base_prefix;
        
        // Define charset and collate
        $charset_collate = $wpdb->get_charset_collate();
        
        // Array of table creation SQL
        $tables = array(
            
            // 1. Business Registrations Table
            "CREATE TABLE {$table_prefix}msme_registrations (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                email varchar(100) NOT NULL,
                google_id varchar(100) NOT NULL,
                business_name varchar(200) NOT NULL,
                subdomain varchar(100) NOT NULL,
                business_category varchar(50) NOT NULL,
                business_address text,
                phone_number varchar(20),
                status enum('pending','approved','rejected') DEFAULT 'pending',
                otp_code varchar(6),
                otp_expires datetime,
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                approved_date datetime,
                admin_notes text,
                PRIMARY KEY (id),
                UNIQUE KEY subdomain (subdomain),
                UNIQUE KEY email (email),
                KEY status (status),
                KEY created_date (created_date),
                KEY business_category (business_category)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 2. Business Profiles Table
            "CREATE TABLE {$table_prefix}msme_business_profiles (
                site_id bigint(20) UNSIGNED NOT NULL,
                owner_name varchar(200),
                business_name varchar(200) NOT NULL,
                business_description text,
                business_category varchar(50) NOT NULL,
                phone_number varchar(20),
                whatsapp_number varchar(20),
                telegram_username varchar(100),
                email varchar(100),
                address text,
                operating_hours json,
                google_maps_url text,
                logo_url varchar(500),
                account_status enum('free','paid') DEFAULT 'free',
                business_status enum('active','temporarily_closed','permanently_closed','moved','inactive') DEFAULT 'active',
                notification_pref enum('email','telegram','both','none') DEFAULT 'email',
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (site_id),
                KEY business_category (business_category),
                KEY business_status (business_status),
                KEY account_status (account_status),
                KEY updated_date (updated_date)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 3. Social Media Links Table
            "CREATE TABLE {$table_prefix}msme_social_media (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id bigint(20) UNSIGNED NOT NULL,
                platform enum('instagram','tiktok','facebook','twitter','youtube','linkedin') NOT NULL,
                username varchar(100),
                url varchar(500),
                display_order int(3) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY site_id (site_id),
                KEY platform (platform),
                KEY is_active (is_active),
                KEY display_order (display_order)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 4. Marketplace Links Table
            "CREATE TABLE {$table_prefix}msme_marketplace_links (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id bigint(20) UNSIGNED NOT NULL,
                platform enum('tokopedia','shopee','gofood','grabfood','shopeefood','custom') NOT NULL,
                store_url varchar(500) NOT NULL,
                store_name varchar(200),
                is_active tinyint(1) DEFAULT 1,
                display_order int(3) DEFAULT 0,
                click_count int(11) DEFAULT 0,
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY site_id (site_id),
                KEY platform (platform),
                KEY is_active (is_active),
                KEY display_order (display_order)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 5. Image Gallery Table
            "CREATE TABLE {$table_prefix}msme_images (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id bigint(20) UNSIGNED NOT NULL,
                filename varchar(255) NOT NULL,
                original_filename varchar(255),
                file_path varchar(500) NOT NULL,
                file_size int(11),
                caption text,
                display_order int(3) DEFAULT 0,
                upload_date datetime DEFAULT CURRENT_TIMESTAMP,
                is_logo tinyint(1) DEFAULT 0,
                PRIMARY KEY (id),
                KEY site_id (site_id),
                KEY display_order (display_order),
                KEY is_logo (is_logo),
                KEY upload_date (upload_date)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 6. Reviews Table (Enhanced)
            "CREATE TABLE {$table_prefix}msme_reviews (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id bigint(20) UNSIGNED NOT NULL,
                reviewer_email varchar(100) NOT NULL,
                reviewer_name varchar(200) NOT NULL,
                reviewer_google_id varchar(100),
                rating tinyint(1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
                review_text text,
                has_images tinyint(1) DEFAULT 0,
                image_count tinyint(1) DEFAULT 0 CHECK (image_count <= 3),
                status enum('pending','approved','rejected') DEFAULT 'pending',
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                moderated_date datetime,
                moderated_by bigint(20) UNSIGNED,
                moderator_notes text,
                helpful_count int(11) DEFAULT 0,
                PRIMARY KEY (id),
                KEY site_id (site_id),
                KEY status (status),
                KEY rating (rating),
                KEY created_date (created_date),
                KEY has_images (has_images),
                UNIQUE KEY unique_reviewer_per_site (site_id, reviewer_google_id)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 7. Review Images Table
            "CREATE TABLE {$table_prefix}msme_review_images (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                review_id bigint(20) UNSIGNED NOT NULL,
                site_id bigint(20) UNSIGNED NOT NULL,
                filename varchar(255) NOT NULL,
                original_filename varchar(255),
                file_path varchar(500) NOT NULL,
                file_size int(11),
                image_order tinyint(1) DEFAULT 1,
                upload_date datetime DEFAULT CURRENT_TIMESTAMP,
                is_approved tinyint(1) DEFAULT 0,
                PRIMARY KEY (id),
                KEY review_id (review_id),
                KEY site_id (site_id),
                KEY is_approved (is_approved),
                KEY image_order (image_order)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 8. Business Categories Table
            "CREATE TABLE {$table_prefix}msme_categories (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                category_name varchar(100) NOT NULL,
                category_slug varchar(100) NOT NULL,
                parent_id bigint(20) UNSIGNED DEFAULT NULL,
                description text,
                icon varchar(100),
                display_order int(3) DEFAULT 0,
                is_active tinyint(1) DEFAULT 1,
                site_count int(11) DEFAULT 0,
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY category_slug (category_slug),
                KEY parent_id (parent_id),
                KEY is_active (is_active),
                KEY display_order (display_order)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 9. Content Reports Table
            "CREATE TABLE {$table_prefix}msme_reports (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id bigint(20) UNSIGNED NOT NULL,
                reporter_email varchar(100),
                report_type enum('closed_business','incorrect_info','inappropriate_content','fake_business','wrong_location','other') NOT NULL,
                description text,
                status enum('pending','investigating','resolved','dismissed') DEFAULT 'pending',
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                resolved_date datetime,
                resolved_by bigint(20) UNSIGNED,
                admin_notes text,
                reporter_ip varchar(45),
                PRIMARY KEY (id),
                KEY site_id (site_id),
                KEY report_type (report_type),
                KEY status (status),
                KEY created_date (created_date),
                KEY reporter_ip (reporter_ip)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 10. Analytics Table
            "CREATE TABLE {$table_prefix}msme_analytics (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id bigint(20) UNSIGNED NOT NULL,
                date date NOT NULL,
                page_views int(11) DEFAULT 0,
                unique_visitors int(11) DEFAULT 0,
                bounce_rate decimal(5,2),
                avg_session_duration int(11),
                review_submissions int(11) DEFAULT 0,
                review_images_uploaded int(11) DEFAULT 0,
                review_images_approved int(11) DEFAULT 0,
                review_images_rejected int(11) DEFAULT 0,
                contact_clicks int(11) DEFAULT 0,
                marketplace_clicks int(11) DEFAULT 0,
                social_clicks int(11) DEFAULT 0,
                whatsapp_clicks int(11) DEFAULT 0,
                telegram_clicks int(11) DEFAULT 0,
                share_clicks int(11) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY unique_site_date (site_id, date),
                KEY date (date),
                KEY site_id (site_id)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 11. Ads Management Table
            "CREATE TABLE {$table_prefix}msme_ads (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ad_name varchar(200) NOT NULL,
                ad_content text NOT NULL,
                ad_type enum('banner','text','image') DEFAULT 'banner',
                target_categories json,
                target_locations json,
                start_date datetime,
                end_date datetime,
                is_active tinyint(1) DEFAULT 1,
                impressions int(11) DEFAULT 0,
                clicks int(11) DEFAULT 0,
                click_rate decimal(5,4) DEFAULT 0.0000,
                daily_budget decimal(10,2),
                total_spent decimal(10,2) DEFAULT 0.00,
                created_date datetime DEFAULT CURRENT_TIMESTAMP,
                updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY is_active (is_active),
                KEY start_date (start_date),
                KEY end_date (end_date),
                KEY ad_type (ad_type)
            ) ENGINE=InnoDB {$charset_collate};",
            
            // 12. Notifications Table
            "CREATE TABLE {$table_prefix}msme_notifications (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient_email varchar(100) NOT NULL,
                recipient_telegram varchar(100),
                notification_type enum('registration','approval','review','status_change','system','marketing') NOT NULL,
                subject varchar(200),
                message text NOT NULL,
                status enum('pending','sent','failed','cancelled') DEFAULT 'pending',
                send_via enum('email','telegram','both') DEFAULT 'email',
                scheduled_date datetime DEFAULT CURRENT_TIMESTAMP,
                sent_date datetime,
                attempts int(3) DEFAULT 0,
                error_message text,
                site_id bigint(20) UNSIGNED,
                PRIMARY KEY (id),
                KEY status (status),
                KEY notification_type (notification_type),
                KEY scheduled_date (scheduled_date),
                KEY recipient_email (recipient_email),
                KEY site_id (site_id)
            ) ENGINE=InnoDB {$charset_collate};"
        );
        
        // Create tables using WordPress dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_sql) {
            $result = dbDelta($table_sql);
            if ($wpdb->last_error) {
                throw new Exception("Database table creation failed: " . $wpdb->last_error);
            }
        }
        
        // Add foreign key constraints separately (after all tables are created)
        $this->add_foreign_keys();
        
        // Add performance indexes
        $this->add_performance_indexes();
    }
    
    /**
     * Add foreign key constraints
     */
    private function add_foreign_keys() {
        global $wpdb;
        
        $table_prefix = $wpdb->base_prefix;
        
        // Check if foreign key constraints already exist
        $existing_constraints = $wpdb->get_results("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND TABLE_NAME IN ('{$table_prefix}msme_review_images', '{$table_prefix}msme_categories')
        ");
    
        $constraint_names = wp_list_pluck($existing_constraints, 'CONSTRAINT_NAME');
        
        // Only add foreign keys if they don't exist
        if (!in_array('fk_review_images_review', $constraint_names)) {
            $wpdb->query("ALTER TABLE {$table_prefix}msme_review_images 
                         ADD CONSTRAINT fk_review_images_review 
                         FOREIGN KEY (review_id) REFERENCES {$table_prefix}msme_reviews(id) ON DELETE CASCADE");
        }
        
        if (!in_array('fk_categories_parent', $constraint_names)) {
            $wpdb->query("ALTER TABLE {$table_prefix}msme_categories 
                         ADD CONSTRAINT fk_categories_parent 
                         FOREIGN KEY (parent_id) REFERENCES {$table_prefix}msme_categories(id) ON DELETE SET NULL");
        }
    }
    
    /**
     * Add performance indexes
     */
    private function add_performance_indexes() {
    global $wpdb;
    
        $table_prefix = $wpdb->base_prefix;
        
        // Define indexes to check/create
        $indexes_to_create = array(
            array('table' => 'msme_business_profiles', 'name' => 'idx_category_status', 'sql' => 'business_category, business_status'),
            array('table' => 'msme_reviews', 'name' => 'idx_site_status_rating', 'sql' => 'site_id, status, rating'),
            array('table' => 'msme_analytics', 'name' => 'idx_site_date_range', 'sql' => 'site_id, date'),
            array('table' => 'msme_marketplace_links', 'name' => 'idx_site_active_platform', 'sql' => 'site_id, is_active, platform'),
            array('table' => 'msme_social_media', 'name' => 'idx_site_active_order', 'sql' => 'site_id, is_active, display_order')
        );
    
        // Check and create regular indexes
        foreach ($indexes_to_create as $index) {
            $table_name = $table_prefix . $index['table'];
            $index_name = $index['name'];
            
            // Check if index exists
            $existing_index = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = '$table_name' 
                AND index_name = '$index_name'
            ");
            
            if (!$existing_index) {
                $wpdb->query("ALTER TABLE $table_name ADD INDEX $index_name ({$index['sql']})");
            }
        }
    
        // Check and create FULLTEXT indexes
        $fulltext_indexes = array(
            array('table' => 'msme_business_profiles', 'columns' => 'business_name, business_description'),
            array('table' => 'msme_reviews', 'columns' => 'review_text')
        );
        
        foreach ($fulltext_indexes as $ft_index) {
            $table_name = $table_prefix . $ft_index['table'];
            
            // Check if FULLTEXT index exists
            $existing_ft = $wpdb->get_var("
                SELECT COUNT(*) 
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE() 
                AND table_name = '$table_name' 
                AND index_type = 'FULLTEXT'
            ");
            
            if (!$existing_ft) {
                $wpdb->query("ALTER TABLE $table_name ADD FULLTEXT({$ft_index['columns']})");
            }
        }
    }
    
    /**
     * Insert initial data
     */
    private function insert_initial_data() {
        global $wpdb;
        
        $table_prefix = $wpdb->base_prefix;
        
        // Check if categories already exist
        $existing_categories = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}msme_categories");
        
        if ($existing_categories == 0) {
            // Insert business categories
            $categories = array(
                array('Makanan & Minuman', 'makanan-minuman', 'Restoran, warung, katering, dan bisnis kuliner', 'fas fa-utensils', 1),
                array('Retail & Toko', 'retail-toko', 'Toko, minimarket, fashion, dan retail', 'fas fa-store', 2),
                array('Kesehatan', 'kesehatan', 'Klinik, apotek, puskesmas, dan layanan kesehatan', 'fas fa-heartbeat', 3),
                array('Pendidikan', 'pendidikan', 'Sekolah, kursus, TK, dan layanan pendidikan', 'fas fa-graduation-cap', 4),
                array('Jasa & Layanan', 'jasa-layanan', 'Salon, bengkel, laundry, dan jasa umum', 'fas fa-tools', 5),
                array('Pemerintahan', 'pemerintahan', 'Kelurahan, kantor desa, dan instansi pemerintah', 'fas fa-landmark', 6),
                array('Otomotif', 'otomotif', 'Bengkel, spare part, dan layanan otomotif', 'fas fa-car', 7),
                array('Teknologi', 'teknologi', 'IT service, komputer, dan teknologi', 'fas fa-laptop', 8),
                array('Kecantikan', 'kecantikan', 'Salon, spa, dan layanan kecantikan', 'fas fa-spa', 9),
                array('Lainnya', 'lainnya', 'Bisnis dan layanan lainnya', 'fas fa-ellipsis-h', 10)
            );
            
            foreach ($categories as $category) {
                $wpdb->insert(
                    $table_prefix . 'msme_categories',
                    array(
                        'category_name' => $category[0],
                        'category_slug' => $category[1],
                        'description' => $category[2],
                        'icon' => $category[3],
                        'display_order' => $category[4],
                        'is_active' => 1,
                        'created_date' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s', '%d', '%d', '%s')
                );
            }
        }
        
        // Check if default ad exists
        $existing_ads = $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}msme_ads");
        
        if ($existing_ads == 0) {
            // Insert default advertisement
            $wpdb->insert(
                $table_prefix . 'msme_ads',
                array(
                    'ad_name' => 'Default Banner Ad',
                    'ad_content' => '<div style="background: #f8f9fa; padding: 20px; text-align: center; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0;"><h4 style="margin: 0 0 10px 0; color: #6c757d;">Ruang Iklan Tersedia</h4><p style="margin: 0; color: #6c757d; font-size: 14px;">Hubungi admin untuk memasang iklan Anda di sini</p></div>',
                    'ad_type' => 'banner',
                    'is_active' => 1,
                    'created_date' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%s')
            );
        }
    }
    
    /**
     * Set plugin options and version
     */
    private function set_plugin_options() {
        // Set plugin version
        update_site_option('msme_plugin_version', MSME_PLUGIN_VERSION);
        
        // Set activation date
        update_site_option('msme_activation_date', current_time('mysql'));
        
        // Set default plugin options
        $default_options = array(
            'image_max_size' => 2097152, // 2MB
            'image_max_count' => 11,
            'review_image_max_count' => 3,
            'auto_approve_reviews' => false,
            'enable_analytics' => true,
            'enable_notifications' => true,
            'default_business_status' => 'active',
            'default_account_status' => 'free',
            'otp_expiry_minutes' => 15,
            'approval_notification_email' => get_site_option('admin_email'),
            'telegram_bot_token' => '',
            'google_maps_api_key' => '',
            'smtp_settings' => array()
        );
        
        update_site_option('msme_plugin_options', $default_options);
        
        $this->set_smtp_options();
    }
    
    /**
     * Create upload directories
     */
    private function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        
        $directories = array(
            $base_dir . '/msme-business',
            $base_dir . '/msme-business/logos',
            $base_dir . '/msme-business/galleries',
            $base_dir . '/msme-reviews',
            $base_dir . '/msme-reviews/' . date('Y'),
            $base_dir . '/msme-reviews/' . date('Y') . '/' . date('m')
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Create .htaccess file to protect direct access
                $htaccess_content = "# Protect uploaded files\n";
                $htaccess_content .= "<Files ~ \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)\$\">\n";
                $htaccess_content .= "deny from all\n";
                $htaccess_content .= "</Files>\n";
                
                file_put_contents($dir . '/.htaccess', $htaccess_content);
                
                // Create index.php to prevent directory listing
                file_put_contents($dir . '/index.php', '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate_plugin() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('msme_daily_analytics');
        wp_clear_scheduled_hook('msme_cleanup_notifications');
        
        // Log deactivation
        error_log('MSME Business Manager: Plugin deactivated');
    }
    
    /**
     * Initialize plugin
     */
    public function init_plugin() {
        // Load text domain for internationalization
        load_plugin_textdomain('msme-business-manager', false, dirname(MSME_PLUGIN_BASENAME) . '/languages');
        
        // Add admin notices
        add_action('network_admin_notices', array($this, 'activation_notice'));
    }
    
    /**
     * Add Network Admin Menu
     */
    public function add_network_admin_menu() {
        add_menu_page(
            'MSME Business Manager',
            'MSME Manager',
            'manage_network',
            'msme-business-manager',
            array($this, 'network_admin_page'),
            'dashicons-store',
            30
        );
        
        add_submenu_page(
            'msme-business-manager',
            'Dashboard',
            'Dashboard',
            'manage_network',
            'msme-business-manager',
            array($this, 'network_admin_page')
        );
        
        add_submenu_page(
            'msme-business-manager',
            'Database Status',
            'Database Status',
            'manage_network',
            'msme-database-status',
            array($this, 'database_status_page')
        );
        
        add_submenu_page(
            'msme-business-manager',
            'Manajemen Pendaftaran',
            'Pendaftaran',
            'manage_network',
            'msme-registrations',
            array($this, 'registration_management_page')  // Following YOUR naming convention
        );
        
        $this->add_smtp_config_page();
    }
    
    /**
     * Add Site Admin Menu
     */
    public function add_site_admin_menu() {
        // Only add for business sites (not main site)
        if (is_main_site()) {
            return;
        }
        
        add_menu_page(
            'Business Manager',
            'Business',
            'manage_options',
            'msme-business',
            array($this, 'site_admin_page'),
            'dashicons-store',
            25
        );
    }
    
    /**
     * Network Admin Page
     */
    public function network_admin_page() {
        ?>
        <div class="wrap">
            <h1>MSME Business Manager - Network Dashboard</h1>
            <div class="card">
                <h2>Plugin Status</h2>
                <p><strong>Version:</strong> <?php echo MSME_PLUGIN_VERSION; ?></p>
                <p><strong>Activation Date:</strong> <?php echo get_site_option('msme_activation_date', 'Not set'); ?></p>
                <p><strong>Database Tables:</strong> <?php echo $this->count_database_tables(); ?> / 12</p>
                <p><strong>Business Categories:</strong> <?php echo $this->count_categories(); ?></p>
            </div>
            
            <div class="card">
                <h2>Quick Actions</h2>
                <button type="button" class="button button-secondary" onclick="testDatabaseConnection()">Test Database Connection</button>
                <button type="button" class="button button-secondary" onclick="debugSMTPTest()" style="margin-left: 10px;">Test SMTP Email</button>
                <div id="db-test-result"></div>
                <div id="smtp-test-result"></div>
            </div>
            
            <div class="card">
                <h2>Database Maintenance</h2>
                <button type="button" class="button button-secondary" onclick="cleanupExpiredRegistrations()">Cleanup Expired Registrations</button>
                <div id="cleanup-result"></div>
            </div>
            
            <script>
            function cleanupExpiredRegistrations() {
                document.getElementById('cleanup-result').innerHTML = '<p>Running cleanup...</p>';
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        document.getElementById('cleanup-result').innerHTML = xhr.responseText;
                    }
                };
                xhr.send('action=manual_cleanup_registrations');
            }
            </script>
            
            <script>
            console.log('Admin page loaded, checking functions...');
            
            // Check if functions exist
            console.log('testSMTPEmail exists:', typeof testSMTPEmail !== 'undefined');
            console.log('ajaxurl available:', typeof ajaxurl !== 'undefined');
            console.log('ajaxurl value:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'NOT SET');
            
            function debugSMTPTest() {
                console.log('Debug SMTP Test clicked');
                
                // Check if our function exists
                if (typeof testSMTPEmail === 'function') {
                    console.log('Calling testSMTPEmail function...');
                    testSMTPEmail();
                } else {
                    console.error('testSMTPEmail function not found!');
                    document.getElementById('smtp-test-result').innerHTML = 
                        '<div style="color: red;">❌ JavaScript function not loaded. Check console for details.</div>';
                }
            }
            
            function testDatabaseConnection() {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        document.getElementById('db-test-result').innerHTML = xhr.responseText;
                    }
                };
                xhr.send('action=msme_test_connection');
            }
            </script>
        <?php
    }
    
    /**
     * Database Status Page
     */
    public function database_status_page() {
        global $wpdb;
        $table_prefix = $wpdb->base_prefix;
        
        // Get table status
        $tables = array(
            'msme_registrations', 'msme_business_profiles', 'msme_social_media',
            'msme_marketplace_links', 'msme_images', 'msme_reviews',
            'msme_review_images', 'msme_categories', 'msme_reports',
            'msme_analytics', 'msme_ads', 'msme_notifications'
        );
        
        ?>
        <div class="wrap">
            <h1>Database Status</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Status</th>
                        <th>Rows</th>
                        <th>Engine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table): ?>
                    <?php
                    $full_table_name = $table_prefix . $table;
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'");
                    $row_count = $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name") : 0;
                    $engine = $table_exists ? $wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$full_table_name'") : '';
                    ?>
                    <tr>
                        <td><?php echo $full_table_name; ?></td>
                        <td><?php echo $table_exists ? '<span style="color: green;">✓ Exists</span>' : '<span style="color: red;">✗ Missing</span>'; ?></td>
                        <td><?php echo $row_count; ?></td>
                        <td><?php echo $engine; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Site Admin Page
     */
    public function site_admin_page() {
        ?>
        <div class="wrap">
            <h1>Business Manager</h1>
            <p>Welcome to your business management dashboard.</p>
            <p><em>Business management features will be available in the next development phase.</em></p>
        </div>
        <?php
    }
    
    /**
     * Display activation notice
     */
    public function activation_notice() {
        if (get_transient('msme_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>MSME Business Manager:</strong> Plugin berhasil diaktivasi! Semua tabel database telah dibuat dan data awal telah dimasukkan.</p>
            </div>
            <?php
            delete_transient('msme_activation_notice');
        }
    }
    
    /**
     * Test database connection (AJAX)
     */
    public function test_database_connection() {
        global $wpdb;
        
        try {
            $result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->base_prefix}msme_categories");
            echo '<p style="color: green;">✓ Database connection successful. Found ' . $result . ' categories.</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">✗ Database connection failed: ' . $e->getMessage() . '</p>';
        }
        
        wp_die();
    }
    
    /**
     * Handle registration page template
     */
    public function handle_registration_page() {
        if (get_query_var('msme_registration')) {
            // Only allow on main site
            if (!is_main_site()) {
                // Return 404 for subsites - registration only on main site
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                return;
            }
            
            // Enqueue assets for registration page
            $this->enqueue_registration_assets();
            
            $this->display_registration_page();
            exit;
        }
    }
    
    /**
     * Count database tables
     */
    private function count_database_tables() {
        global $wpdb;
        $table_prefix = $wpdb->base_prefix;
        return $wpdb->get_var("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '{$table_prefix}msme_%'");
    }
    
    /**
     * Registration endpoint and form handling
     */
    public function init_registration_system() {
        // Add custom endpoint for business registration
        add_action('init', array($this, 'add_registration_endpoint'));
        add_action('template_redirect', array($this, 'handle_registration_page'));
    }
    
    /**
     * Add custom rewrite rule for registration
     */
    public function add_registration_endpoint() {
        add_rewrite_rule('^daftar-bisnis/?$', 'index.php?msme_registration=1', 'top');
        add_rewrite_tag('%msme_registration%', '([^&]+)');
        
        // Flush rewrite rules if needed (only on activation)
        if (get_option('msme_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('msme_flush_rewrite_rules');
        }
    }
    
    /**
     * Enqueue assets for registration page
     */
    private function enqueue_registration_assets() {
        // Enqueue CSS
        wp_enqueue_style(
            'msme-registration',
            MSME_PLUGIN_URL . 'assets/css/registration.css',
            array(),
            MSME_PLUGIN_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'msme-registration',
            MSME_PLUGIN_URL . 'assets/js/registration.js',
            array('jquery'),
            MSME_PLUGIN_VERSION,
            true
        );
        
        // Localize script for AJAX
        $localize_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('msme_registration_nonce')
        );
        
        // Add current user data if logged in
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $localize_data['current_user'] = array(
                'display_name' => $current_user->display_name,
                'email' => $current_user->user_email,
                'id' => $current_user->ID
            );
        }
        
        wp_localize_script('msme-registration', 'msme_ajax', $localize_data);
    }
    
    /**
     * Display registration page
     */
    private function display_registration_page() {
        // Check if user just completed Google auth
        $google_auth_success = isset($_GET['google_auth']) && $_GET['google_auth'] === 'success';
        $step = isset($_GET['step']) ? $_GET['step'] : '1';
        
        
        
        // Auto-advance to step 2 if Google auth successful
        if ($google_auth_success && is_user_logged_in()) {
            $step = '2';
        }
        
        // Complete HTML template - no theme dependency
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Daftar Bisnis - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
        </head>
        <body <?php body_class('msme-registration'); ?>>
            
        <div class="msme-registration-container">
            <div class="container">
                <h1>Daftar Bisnis Anda</h1>
                <p>Buat landing page gratis untuk bisnis lokal Anda di Indonesia</p>
                
                <div class="registration-info">
                    <h2><span class="icon-target">●</span> Test Phase: Registration Endpoint</h2>
                    <p><strong>Status:</strong> <span class="icon-check">✓</span> Custom endpoint working</p>
                    <p><strong>URL:</strong> <?php echo home_url('/daftar-bisnis'); ?></p>
                    <p><strong>Site ID:</strong> <?php echo get_current_blog_id(); ?></p>
                    <p><strong>Is Main Site:</strong> <?php echo is_main_site() ? 'Yes' : 'No'; ?></p>
                </div>
                
                <!-- Registration Form -->
                <div class="registration-form-container">
                    <div class="registration-steps">
                        <div class="step <?php echo ($step == '1') ? 'active' : ''; ?>" id="step-1">
                            <span class="step-number">1</span>
                            <span class="step-text">Login dengan Google</span>
                        </div>
                        <div class="step <?php echo ($step == '2') ? 'active' : ''; ?>" id="step-2">
                            <span class="step-number">2</span>
                            <span class="step-text">Informasi Bisnis</span>
                        </div>
                        <div class="step <?php echo ($step == '3') ? 'active' : ''; ?>" id="step-3">
                            <span class="step-number">3</span>
                            <span class="step-text">Verifikasi Email</span>
                        </div>
                    </div>
    
                    <!-- Step 1: Google Authentication -->
                    <div class="form-step" id="form-step-1" <?php echo ($step != '1') ? 'style="display: none;"' : ''; ?>>
                        <h3>Masuk dengan Akun Google Anda</h3>
                        <p>Gunakan akun Google untuk mendaftar bisnis Anda secara aman dan mudah.</p>
                        
                        <div class="google-login-container">
                            <?php
                            // Check if user is already logged in
                            if (is_user_logged_in()) {
                                $current_user = wp_get_current_user();
                                echo '<div class="user-logged-in">';
                                echo '<p><strong>Selamat datang, ' . esc_html($current_user->display_name) . '!</strong></p>';
                                echo '<p>Email: ' . esc_html($current_user->user_email) . '</p>';
                                
                                // Check if they just completed Google auth
                                if ($google_auth_success) {
                                    echo '<p style="color: green;">✓ Login Google berhasil! Silakan lengkapi informasi bisnis Anda.</p>';
                                    echo '<script>
                                        document.addEventListener("DOMContentLoaded", function() {
                                            showStep(2);
                                            // Pre-fill user data
                                            document.getElementById("owner_name").value = "' . esc_js($current_user->display_name) . '";
                                            document.getElementById("owner_email").value = "' . esc_js($current_user->user_email) . '";
                                        });
                                    </script>';
                                } else {
                                    echo '<button type="button" class="btn-continue" onclick="continueToBusinessForm()">Lanjutkan Pendaftaran Bisnis</button>';
                                }
                                
                                echo '<p><a href="' . wp_logout_url(home_url('/daftar-bisnis')) . '">Gunakan akun Google lain</a></p>';
                                echo '</div>';
                            } else {
                                // Show Google login button - use Nextend's redirect setting
                                echo '<a href="' . wp_login_url() . '?loginSocial=google" class="google-login-btn">';
                                echo '<img src="https://developers.google.com/identity/images/g-logo.png" alt="Google"> ';
                                echo 'Masuk dengan Google</a>';
                            }
                            ?>
                        </div>
                        
                        <div class="debug-info" style="background: #f0f8ff; padding: 15px; margin: 15px 0; border-radius: 5px; font-size: 12px;">
                            <strong>Debug - Current User Data:</strong><br>
                            <?php 
                            $current_user = wp_get_current_user();
                            echo 'Display Name: ' . $current_user->display_name . '<br>';
                            echo 'Email: ' . $current_user->user_email . '<br>';
                            echo 'User ID: ' . $current_user->ID . '<br>';
                            ?>
                        </div>
                        
                        <div class="login-info">
                            <h4>Mengapa menggunakan Google?</h4>
                            <ul>
                                <li>✓ Proses registrasi lebih cepat</li>
                                <li>✓ Keamanan data terjamin</li>
                                <li>✓ Tidak perlu mengingat password baru</li>
                                <li>✓ Informasi profil otomatis terisi</li>
                            </ul>
                        </div>
                    </div>
    
                    <!-- Step 2: Business Information -->
                    <div class="form-step" id="form-step-2" <?php echo ($step != '2') ? 'style="display: none;"' : ''; ?>>
                        <h3>Informasi Bisnis Anda</h3>
                        <p>Lengkapi informasi bisnis untuk membuat landing page yang menarik.</p>
                        
                        <form id="business-registration-form" method="post">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="owner_name">Nama Pemilik Bisnis *</label>
                                    <input type="text" id="owner_name" name="owner_name" required 
                                           placeholder="Nama akan otomatis terisi dari Google">
                                    <small>Anda dapat mengedit nama jika diperlukan</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="owner_email">Email *</label>
                                    <input type="email" id="owner_email" name="owner_email" required readonly
                                           placeholder="Email akan otomatis terisi dari Google">
                                    <small>Email tidak dapat diubah (dari akun Google Anda)</small>
                                </div>
                            </div>
    
                            <div class="form-group">
                                <label for="business_name">Nama Bisnis *</label>
                                <input type="text" id="business_name" name="business_name" required 
                                       placeholder="Contoh: Warung Makan Sederhana" maxlength="200">
                                <small>Nama bisnis yang akan ditampilkan di landing page</small>
                            </div>
    
                            <div class="form-group">
                                <label for="business_category">Kategori Bisnis *</label>
                                <select id="business_category" name="business_category" required>
                                    <option value="">Pilih kategori bisnis Anda</option>
                                    <?php
                                    // Load categories from database
                                    global $wpdb;
                                    $categories = $wpdb->get_results("SELECT category_slug, category_name FROM {$wpdb->base_prefix}msme_categories WHERE is_active = 1 ORDER BY display_order");
                                    foreach ($categories as $category) {
                                        echo '<option value="' . esc_attr($category->category_slug) . '">' . esc_html($category->category_name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone_number">Nomor Telepon</label>
                                    <input type="tel" id="phone_number" name="phone_number" 
                                           placeholder="08123456789" pattern="[0-9]{10,15}">
                                </div>
                                
                                <div class="form-group">
                                    <label for="business_address">Alamat Bisnis</label>
                                    <input type="text" id="business_address" name="business_address" 
                                           placeholder="Jl. Menteng Dalam No. 123, Jakarta" maxlength="200">
                                    <small>Alamat akan membantu membuat saran subdomain yang unik</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="subdomain">Alamat Website (Subdomain) *</label>
                                <div class="subdomain-suggestions" id="subdomain-suggestions" style="display: none;">
                                    <p><strong>Saran subdomain berdasarkan nama bisnis dan alamat:</strong></p>
                                    <div class="suggestion-buttons" id="suggestion-buttons"></div>
                                    <p><em>Atau masukkan subdomain pilihan Anda di bawah:</em></p>
                                </div>
                                <div class="subdomain-input">
                                    <input type="text" id="subdomain" name="subdomain" required 
                                        placeholder="namatoko" pattern="[a-z0-9\-]+" maxlength="50">
                                    <span class="domain-suffix">.cobalah.id</span>
                                </div>
                                <div id="subdomain-check" class="subdomain-check"></div>
                                <small>Hanya huruf kecil, angka, dan tanda hubung (-). Minimal 3 karakter.</small>
                            </div>
    
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="terms_agree" name="terms_agree" required>
                                    <span class="checkmark"></span>
                                    Saya setuju dengan <a href="#" target="_blank">Syarat & Ketentuan</a> dan <a href="#" target="_blank">Kebijakan Privasi</a>
                                </label>
                            </div>
    
                            <button type="submit" class="btn-submit" id="submit-registration" disabled>
                                <span class="btn-text">Daftar Sekarang</span>
                                <span class="btn-loading" style="display: none;">Memproses...</span>
                            </button>
                        </form>
                    </div>
    
                    <!-- Step 3: Email Verification -->
                    <div class="form-step" id="form-step-3" <?php echo ($step != '3') ? 'style="display: none;"' : ''; ?>>
                        <h3>Verifikasi Email</h3>
                        
                        <!-- Clear Gmail Instructions -->
                        <div class="email-check-instructions">
                            <div class="instruction-header">
                                <h4 style="color: #0073aa; margin: 0 0 15px 0;">[EMAIL] Silakan Cek Email Gmail Anda Sekarang!</h4>
                            </div>
                            
                            <div class="step-by-step-guide">
                                <div class="instruction-step">
                                    <span class="step-number">1</span>
                                    <div class="step-content">
                                        <strong>Buka Gmail Anda</strong><br>
                                        <small>Gunakan aplikasi Gmail di HP atau buka <a href="https://gmail.com" target="_blank" style="color: #0073aa; font-weight: bold;">gmail.com</a> di browser</small>
                                    </div>
                                </div>
                                
                                <div class="instruction-step">
                                    <span class="step-number">2</span>
                                    <div class="step-content">
                                        <strong>Cari email dengan subjek:</strong><br>
                                        <div class="email-subject-box">
                                            <strong>[Cobalah.id] OTP Kode Verifikasi</strong>
                                        </div>
                                        <small>Email ini dikirim dari: tidak-dibalas@cobalah.id</small>
                                    </div>
                                </div>
                                
                                <div class="instruction-step">
                                    <span class="step-number">3</span>
                                    <div class="step-content">
                                        <strong>Ambil kode 6 angka</strong><br>
                                        <small>Di dalam email tersebut, akan ada <strong>kode 6 angka</strong> (contoh: 123456)</small>
                                    </div>
                                </div>
                                
                                <div class="instruction-step">
                                    <span class="step-number">4</span>
                                    <div class="step-content">
                                        <strong>Masukkan kode di bawah ini</strong><br>
                                        <small>Ketik kode 6 angka tersebut pada kolom di bawah</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Troubleshooting Tips -->
                            <div class="troubleshooting-tips">
                                <h4 style="color: #d63384; margin: 15px 0 10px 0;">[!] Tidak Menemukan Email?</h4>
                                <ul style="margin: 0; padding-left: 20px; font-size: 14px;">
                                    <li><strong>Cek folder Spam/Junk</strong> - Email mungkin masuk ke spam</li>
                                    <li><strong>Cek tab Promosi</strong> - Jika menggunakan Gmail di browser</li>
                                    <li><strong>Tunggu 1-2 menit</strong> - Email kadang butuh waktu untuk sampai</li>
                                    <li><strong>Refresh/muat ulang</strong> kotak masuk Gmail Anda</li>
                                </ul>
                            </div>
                        </div>
                        
                        <form id="email-verification-form">
                            <div class="form-group">
                                <label for="otp_code">[#] Masukkan Kode 6 Angka dari Email *</label>
                                <input type="text" id="otp_code" name="otp_code" required 
                                       placeholder="123456" maxlength="6" pattern="[0-9]{6}"
                                       style="font-size: 24px; text-align: center; letter-spacing: 5px; font-family: monospace;">
                                <small>Contoh: 123456 (tanpa spasi atau tanda baca)</small>
                            </div>
                            
                            <button type="submit" class="btn-submit">[✓] Verifikasi Email</button>
                            
                            <!-- After your existing verification button, ADD: -->
                            <div class="resend-section" style="margin-top: 20px; text-align: center; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                                <p style="color: #666; margin: 10px 0;">Tidak menerima kode?</p>
                                <button type="button" class="resend-otp-btn" style="background: transparent; color: #0073aa; border: 1px solid #0073aa; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                                    Kirim Ulang Kode Verifikasi
                                </button>
                            </div>
                            
                            <!-- Help Section -->
                            <div class="help-section">
                                <p style="text-align: center; margin: 25px 0 0 0; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 13px;">
                                    <strong>[?] Apa itu Kode Verifikasi (OTP)?</strong><br>
                                    Kode verifikasi adalah <strong>6 angka rahasia</strong> yang kami kirim ke email Anda untuk memastikan 
                                    bahwa email tersebut benar-benar milik Anda. Ini untuk keamanan akun bisnis Anda.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
    
    /**
     * Enqueue assets for admin pages
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'msme-') === false) {
            return;
        }
        
        wp_enqueue_script(
            'msme-admin',
            MSME_PLUGIN_URL . 'assets/js/registration.js',
            array('jquery'),
            MSME_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('msme-admin', 'msme_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('msme_admin_nonce')
        ));
        
        echo '<script>console.log("MSME admin JS loaded on: ' . $hook . '");</script>';
    }
    
    /**
     * Handle post-login redirect for business registration
     */
    public function handle_registration_login_redirect($redirect_to, $request, $user) {
        // Only handle for non-admin users logging in via Google
        if (is_wp_error($user)) {
            return $redirect_to;
        }
        
        // Check if this is a social login (Nextend adds user meta)
        $is_social_login = get_user_meta($user->ID, 'nsl-google-id', true);
        
        // Check if user came from registration page
        $referer = wp_get_referer();
        if ($is_social_login || (isset($_REQUEST['loginSocial']) && $_REQUEST['loginSocial'] === 'google') || strpos($referer, 'daftar-bisnis') !== false) {
            
            // Check if user has completed business registration
            global $wpdb;
            $existing_registration = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->base_prefix}msme_registrations WHERE email = %s OR google_id = %s",
                $user->user_email,
                get_user_meta($user->ID, 'nsl-google-id', true)
            ));
            
            if (!$existing_registration) {
                // First time - redirect to registration form step 2
                return home_url('/daftar-bisnis?step=2&google_auth=success');
            } else {
                // Already registered - check status
                if ($existing_registration->status === 'pending') {
                    return home_url('/daftar-bisnis?step=pending');
                } elseif ($existing_registration->status === 'approved') {
                    // Find their business site
                    $site_domain = $existing_registration->subdomain . '.cobalah.id';
                    $site = get_site_by_path($site_domain, '/');
                    if ($site) {
                        return get_site_url($site->blog_id);
                    }
                }
            }
        }
        
        return $redirect_to;
    }

    /**
     * Initialize login redirect handling
     */
    public function init_login_redirect() {
        add_filter('login_redirect', array($this, 'handle_registration_login_redirect'), 10, 3);
    }

    /**
     * Count categories
     */
    private function count_categories() {
        global $wpdb;
        $table_prefix = $wpdb->base_prefix;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$table_prefix}msme_categories");
    }
    
    /**
     * AJAX handler for checking subdomain availability
     */
    public function check_subdomain_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'msme_registration_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        $subdomain = sanitize_text_field($_POST['subdomain']);
        
        // Validate subdomain format
        if (!preg_match('/^[a-z0-9-]+$/', $subdomain) || strlen($subdomain) < 3) {
            wp_send_json_error(array('message' => 'Invalid subdomain format'));
            return;
        }
        
        global $wpdb;
        
        // Run cleanup for expired registrations
        $this->cleanup_expired_registrations();
        
        // Check if subdomain exists in active registrations only
        $existing_registration = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->base_prefix}msme_registrations 
             WHERE subdomain = %s AND status IN ('verified', 'approved')",
            $subdomain
        ));
        
        if ($existing_registration) {
            wp_send_json_success(array('available' => false, 'reason' => 'Already registered'));
            return;
        }
        
        // Check if subdomain exists in WordPress multisite
        $existing_site = get_site_by_path($subdomain . '.cobalah.id', '/');
        
        if ($existing_site) {
            wp_send_json_success(array('available' => false, 'reason' => 'Site already exists'));
            return;
        }
        
        // Check reserved subdomains
        $reserved_subdomains = array('www', 'admin', 'api', 'mail', 'ftp', 'blog', 'shop', 'store', 'news', 'support', 'help');
        if (in_array($subdomain, $reserved_subdomains)) {
            wp_send_json_success(array('available' => false, 'reason' => 'Reserved subdomain'));
            return;
        }
        
        wp_send_json_success(array('available' => true));
    }
    
    /**
     * Configure Gmail SMTP for sending emails
     */
    public function configure_gmail_smtp() {
        add_action('phpmailer_init', array($this, 'setup_phpmailer_smtp'));
    }
    
    /**
     * Setup PHPMailer with Gmail SMTP
     */
    public function setup_phpmailer_smtp($phpmailer) {
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.gmail.com';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = 587;
        $phpmailer->SMTPSecure = 'tls';
        $phpmailer->SMTPDebug = 0; // Set to 2 for debugging if needed
        
        // Get SMTP credentials from plugin options
        $plugin_options = get_site_option('msme_plugin_options', array());
        $smtp_user = isset($plugin_options['smtp_user']) ? $plugin_options['smtp_user'] : '';
        $smtp_pass = isset($plugin_options['smtp_pass']) ? $plugin_options['smtp_pass'] : '';
        
        if (!empty($smtp_user) && !empty($smtp_pass)) {
            $phpmailer->Username = $smtp_user;
            $phpmailer->Password = $smtp_pass;
            $phpmailer->From = 'tidak-dibalas@cobalah.id';
            $phpmailer->FromName = 'Cobalah.id - Website Gratis untuk UMKM Indonesia';
            $phpmailer->addReplyTo('bantuan@cobalah.id', 'Tim Support Cobalah.id');
        }
    }
    
    /**
     * Add SMTP settings to plugin options during activation
     */
    private function set_smtp_options() {
        $plugin_options = get_site_option('msme_plugin_options', array());
        
        // Add SMTP settings if not exist
        if (!isset($plugin_options['smtp_user'])) {
            $plugin_options['smtp_user'] = ''; // Will be set via admin panel
            $plugin_options['smtp_pass'] = ''; // Will be set via admin panel
            $plugin_options['smtp_from_name'] = 'Cobalah.id - Website Gratis untuk UMKM Indonesia';
            $plugin_options['smtp_from_email'] = 'tidak-dibalas@cobalah.id';
            $plugin_options['smtp_support_email'] = 'bantuan@cobalah.id';
            
            update_site_option('msme_plugin_options', $plugin_options);
        }
    }
    
    /**
     * Send OTP email to user with clear Indonesian instructions
     */
    private function send_otp_email($email, $name, $otp_code, $business_name) {
        $subject = '[Cobalah.id] OTP Kode Verifikasi - Registrasi Bisnis Anda';
        
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.8; color: #333; background: #f8f9fa;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #0073aa, #005a87); padding: 30px 20px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 28px;'>Cobalah.id</h1>
                    <p style='color: #e6f3ff; margin: 5px 0 0 0; font-size: 14px;'>Website Gratis untuk UMKM Indonesia</p>
                </div>
                
                <!-- Content -->
                <div style='padding: 30px 25px;'>
                    <h2 style='color: #0073aa; margin: 0 0 20px 0; font-size: 24px;'>Selamat datang, {$name}!</h2>
                    
                    <p style='margin: 0 0 20px 0; font-size: 16px;'>
                        Terima kasih telah mendaftarkan bisnis <strong style='color: #0073aa;'>\"{$business_name}\"</strong> 
                        di platform Cobalah.id. Kami sangat senang dapat membantu bisnis Anda hadir secara online!
                    </p>
                    
                    <!-- OTP Section -->
                    <div style='background: #f0f8ff; border: 2px solid #0073aa; border-radius: 10px; padding: 25px; text-align: center; margin: 25px 0;'>
                        <h3 style='margin: 0 0 15px 0; color: #0073aa; font-size: 18px;'>🔐 Kode Verifikasi Email Anda</h3>
                        <div style='background: white; border-radius: 8px; padding: 20px; margin: 15px 0;'>
                            <h1 style='font-size: 42px; letter-spacing: 8px; color: #0073aa; margin: 0; font-weight: bold; font-family: monospace;'>{$otp_code}</h1>
                        </div>
                        <p style='margin: 15px 0 0 0; color: #d63384; font-weight: bold; font-size: 14px;'>
                            ⏰ Kode ini berlaku selama 15 menit saja
                        </p>
                    </div>
                    
                    <!-- Instructions -->
                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 25px 0;'>
                        <h4 style='margin: 0 0 15px 0; color: #856404; font-size: 16px;'>📋 Langkah Selanjutnya:</h4>
                        <ol style='margin: 0; padding-left: 20px; color: #856404;'>
                            <li style='margin-bottom: 8px;'><strong>Kembali ke halaman registrasi</strong> di browser Anda</li>
                            <li style='margin-bottom: 8px;'><strong>Masukkan kode 6 digit</strong> di atas pada kolom \"Kode Verifikasi\"</li>
                            <li style='margin-bottom: 8px;'><strong>Klik tombol \"Verifikasi Email\"</strong></li>
                            <li style='margin-bottom: 0;'><strong>Tunggu persetujuan admin</strong> (biasanya dalam 24 jam)</li>
                        </ol>
                    </div>
                    
                    <!-- What happens next -->
                    <div style='background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 20px; margin: 25px 0;'>
                        <h4 style='margin: 0 0 10px 0; color: #0c5460; font-size: 16px;'>🎯 Setelah Verifikasi Berhasil:</h4>
                        <ul style='margin: 0; padding-left: 20px; color: #0c5460;'>
                            <li style='margin-bottom: 5px;'>Pendaftaran Anda akan masuk ke antrean persetujuan admin</li>
                            <li style='margin-bottom: 5px;'>Kami akan kirim email pemberitahuan status persetujuan</li>
                            <li style='margin-bottom: 0;'>Setelah disetujui, landing page bisnis Anda akan langsung aktif!</li>
                        </ul>
                    </div>
                    
                    <!-- No Reply Warning -->
                    <div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 25px 0;'>
                        <p style='margin: 0; font-size: 14px; color: #721c24; text-align: center;'>
                            <strong>⚠️ PENTING:</strong> Email ini tidak dapat dibalas (no-reply)<br>
                            <strong>Jangan balas email ini</strong>
                        </p>
                    </div>
                    
                    <!-- Support -->
                    <div style='background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin: 25px 0;'>
                        <h4 style='margin: 0 0 10px 0; color: #155724; font-size: 16px;'>🆘 Butuh Bantuan?</h4>
                        <p style='margin: 0 0 10px 0; color: #155724;'>
                            <strong>Email Support:</strong> <a href='mailto:bantuan@cobalah.id' style='color: #155724; font-weight: bold;'>bantuan@cobalah.id</a>
                        </p>
                        <p style='margin: 0 0 10px 0; color: #155724;'>
                            <strong>Tim kami siap membantu Anda</strong> dengan segala pertanyaan seputar registrasi dan penggunaan platform.
                        </p>
                        <p style='margin: 0; font-size: 13px; color: #155724; font-style: italic;'>
                            * Nomor WhatsApp support akan segera tersedia
                        </p>
                    </div>
                    
                    <!-- Additional Info -->
                    <div style='border-top: 2px dashed #dee2e6; padding-top: 20px; margin-top: 30px;'>
                        <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>
                            <strong>Tidak merasa mendaftar?</strong> Abaikan email ini dengan aman. 
                            Kode verifikasi akan otomatis kedaluwarsa dalam 15 menit.
                        </p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style='background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6;'>
                    <p style='margin: 0 0 5px 0; font-size: 12px; color: #6c757d;'>
                        <strong>Email otomatis dari Cobalah.id</strong><br>
                        Website Gratis untuk UMKM Indonesia
                    </p>
                    <p style='margin: 0; font-size: 11px; color: #6c757d;'>
                        Dikirim dari: tidak-dibalas@cobalah.id | Support: bantuan@cobalah.id
                    </p>
                </div>
                
            </div>
        </body>
        </html>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Cobalah.id - Website Gratis untuk UMKM Indonesia <tidak-dibalas@cobalah.id>',
            'Reply-To: bantuan@cobalah.id',
            'X-Mailer: Cobalah.id Registration System'
        );
        
        return wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Add SMTP configuration to network admin
     */
    public function add_smtp_config_page() {
        add_submenu_page(
            'msme-business-manager',
            'SMTP Configuration',
            'SMTP Settings',
            'manage_network',
            'msme-smtp-config',
            array($this, 'smtp_config_page')
        );
    }
    
    /**
     * Display SMTP configuration page
     */
    public function smtp_config_page() {
        if (isset($_POST['save_smtp'])) {
            $plugin_options = get_site_option('msme_plugin_options', array());
            $plugin_options['smtp_user'] = sanitize_email($_POST['smtp_user']);
            $plugin_options['smtp_pass'] = sanitize_text_field($_POST['smtp_pass']);
            update_site_option('msme_plugin_options', $plugin_options);
            
            echo '<div class="notice notice-success"><p>SMTP settings saved successfully!</p></div>';
        }
        
        $plugin_options = get_site_option('msme_plugin_options', array());
        $smtp_user = isset($plugin_options['smtp_user']) ? $plugin_options['smtp_user'] : '';
        
        ?>
        <div class="wrap">
            <h1>SMTP Configuration</h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">Gmail Address</th>
                        <td>
                            <input type="email" name="smtp_user" value="<?php echo esc_attr($smtp_user); ?>" class="regular-text" />
                            <p class="description">Gmail address for sending emails</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Gmail App Password</th>
                        <td>
                            <input type="password" name="smtp_pass" value="" class="regular-text" />
                            <p class="description">16-character app password from Gmail (not your regular password)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save SMTP Settings', 'primary', 'save_smtp'); ?>
            </form>
            
            <h2>Setup Instructions</h2>
            <ol>
                <li>Go to Google Account Settings → Security</li>
                <li>Enable 2-Step Verification</li>
                <li>Go to App passwords</li>
                <li>Generate password for "Mail"</li>
                <li>Copy the 16-character password and paste above</li>
            </ol>
        </div>
        <?php
    }
    
    /**
     * Test SMTP email sending with comprehensive logging
     */
    public function test_smtp_email() {
        // Security check
        if (!current_user_can('manage_network')) {
            wp_die('Unauthorized access');
        }
        
        echo '<div style="font-family: monospace; background: #f9f9f9; padding: 20px; border-radius: 5px;">';
        echo '<h3>[EMAIL] SMTP Test Process Log</h3>';
        
        // Step 1: Check SMTP configuration
        echo '<p><strong>Step 1:</strong> Checking SMTP configuration...</p>';
        $plugin_options = get_site_option('msme_plugin_options', array());
        
        if (empty($plugin_options['smtp_user']) || empty($plugin_options['smtp_pass'])) {
            echo '<div style="color: red;">[X] SMTP credentials not configured!</div>';
            echo '<p>Please go to Network Admin → MSME Manager → SMTP Settings and configure Gmail credentials.</p>';
            echo '</div>';
            wp_die();
        }
        
        echo '<div style="color: green;">[✓] SMTP credentials found</div>';
        echo '<p>SMTP User: ' . $plugin_options['smtp_user'] . '</p>';
        
        // Step 2: Test email configuration
        echo '<p><strong>Step 2:</strong> Preparing test email...</p>';
        
        // Updated test email configuration as requested
        $test_email = isset($_POST['test_email']) && !empty($_POST['test_email']) 
            ? sanitize_email($_POST['test_email']) 
            : 'rizahnst@gmail.com'; // Updated default as shown in your image
        
        $subject = '[SMTP Test] Cobalah.id - ' . current_time('Y-m-d H:i:s');
        
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #0073aa, #005a87); padding: 25px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 24px;'>&#x1F4E7; SMTP Configuration Test</h1>
                    <p style='color: #e6f3ff; margin: 5px 0 0 0;'>Cobalah.id - Website Gratis untuk UMKM Indonesia</p>
                </div>
                
                <!-- Content -->
                <div style='padding: 25px;'>
                    <div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin-bottom: 20px;'>
                        <h3 style='margin: 0 0 10px 0; color: #155724;'>&#x2705; SMTP Configuration Working!</h3>
                        <p style='margin: 0; color: #155724;'>Your Gmail SMTP setup is functioning correctly.</p>
                    </div>
                    
                    <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold; width: 120px;'>&#x1F4E4; From:</td>
                            <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>tidak-dibalas@cobalah.id</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>&#x1F4E5; To:</td>
                            <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>{$test_email}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>&#x23F0; Time:</td>
                            <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . current_time('Y-m-d H:i:s') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;'>&#x1F4BB; Server:</td>
                            <td style='padding: 8px; border-bottom: 1px solid #dee2e6;'>" . $_SERVER['HTTP_HOST'] . "</td>
                        </tr>
                    </table>
                    
                    <div style='background: #f8f9fa; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 20px;'>
                        <h4 style='margin: 0 0 10px 0; color: #0073aa;'>&#x2139; Next Steps:</h4>
                        <ul style='margin: 0; padding-left: 20px; color: #495057;'>
                            <li>SMTP configuration is working properly</li>
                            <li>Emails will be delivered to recipient inboxes</li>
                            <li>OTP verification emails are ready to use</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style='background: #f8f9fa; padding: 15px; text-align: center; border-top: 1px solid #dee2e6;'>
                    <p style='margin: 0; font-size: 12px; color: #6c757d;'>
                        <strong>Email otomatis dari Cobalah.id</strong><br>
                        Support: bantuan@cobalah.id
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Cobalah.id - Website Gratis untuk UMKM Indonesia <tidak-dibalas@cobalah.id>',
            'Reply-To: bantuan@cobalah.id'
        );
        
        echo '<div style="color: green;">[✓] Email prepared</div>';
        echo '<p>To: ' . $test_email . '</p>';
        echo '<p>Subject: ' . $subject . '</p>';
        
        // Step 3: Send email with error capturing
        echo '<p><strong>Step 3:</strong> Attempting to send email...</p>';
        
        // Capture any PHP errors
        ob_start();
        $sent = wp_mail($test_email, $subject, $message, $headers);
        $email_errors = ob_get_clean();
        
        if ($email_errors) {
            echo '<div style="color: orange;">[!] PHP Warnings/Errors:</div>';
            echo '<pre style="background: #fff3cd; padding: 10px;">' . htmlspecialchars($email_errors) . '</pre>';
        }
        
        // Step 4: Check result
        echo '<p><strong>Step 4:</strong> Checking send result...</p>';
        
        if ($sent) {
            echo '<div style="color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            echo '<strong>[✓] Email Sent Successfully!</strong><br>';
            echo 'Check your inbox: ' . $test_email . '<br>';
            echo 'Also check spam folder if not in inbox.';
            echo '</div>';
        } else {
            echo '<div style="color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;">';
            echo '<strong>[X] Email Sending Failed!</strong>';
            echo '</div>';
            
            // Additional debugging
            global $phpmailer;
            if (isset($phpmailer)) {
                echo '<p><strong>PHPMailer Error Info:</strong></p>';
                echo '<pre style="background: #f8d7da; padding: 10px;">';
                echo 'ErrorInfo: ' . $phpmailer->ErrorInfo . "\n";
                echo 'Host: ' . $phpmailer->Host . "\n";
                echo 'Port: ' . $phpmailer->Port . "\n";
                echo 'Username: ' . $phpmailer->Username . "\n";
                echo 'SMTPAuth: ' . ($phpmailer->SMTPAuth ? 'Yes' : 'No') . "\n";
                echo '</pre>';
            }
        }
        
        // Step 5: Test SMTP connection directly
        echo '<p><strong>Step 5:</strong> Testing direct SMTP connection...</p>';
        
        $smtp_host = 'smtp.gmail.com';
        $smtp_port = 587;
        
        $connection = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
        if ($connection) {
            echo '<div style="color: green;">[✓] Direct SMTP connection successful</div>';
            fclose($connection);
        } else {
            echo '<div style="color: red;">[X] Direct SMTP connection failed: ' . $errstr . ' (Error: ' . $errno . ')</div>';
        }
        
        echo '</div>';
        wp_die();
    }
    
    /**
     * Handle business registration submission
     */
    public function submit_business_registration() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'msme_registration_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Validate required fields
        $required_fields = array('owner_name', 'owner_email', 'business_name', 'business_category', 'subdomain');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => "Field {$field} is required"));
                return;
            }
        }
        
        // Sanitize input data
        $owner_name = sanitize_text_field($_POST['owner_name']);
        $owner_email = sanitize_email($_POST['owner_email']);
        $business_name = sanitize_text_field($_POST['business_name']);
        $business_category = sanitize_text_field($_POST['business_category']);
        $subdomain = sanitize_text_field($_POST['subdomain']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $business_address = sanitize_textarea_field($_POST['business_address']);
        
        // Validate email
        if (!is_email($owner_email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
            return;
        }
        
        // Validate subdomain format
        if (!preg_match('/^[a-z0-9-]+$/', $subdomain) || strlen($subdomain) < 3) {
            wp_send_json_error(array('message' => 'Invalid subdomain format'));
            return;
        }
        
        // Clean up any expired/abandoned registrations first
        $this->prepare_clean_registration($owner_email, $subdomain);
        
        global $wpdb;
        
        // Check subdomain availability (only approved/verified registrations)
        $existing_registration = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->base_prefix}msme_registrations 
             WHERE subdomain = %s AND status IN ('verified', 'approved')",
            $subdomain
        ));
        
        if ($existing_registration) {
            wp_send_json_error(array('message' => 'Subdomain sudah digunakan oleh bisnis yang aktif'));
            return;
        }
        
        // Check in WordPress multisite
        $existing_site = get_site_by_path($subdomain . '.cobalah.id', '/');
        if ($existing_site) {
            wp_send_json_error(array('message' => 'Subdomain sudah ada di sistem'));
            return;
        }
        
        // Check if email already has verified/approved registration
        $existing_email = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->base_prefix}msme_registrations 
             WHERE email = %s AND status IN ('verified', 'approved')",
            $owner_email
        ));
        
        if ($existing_email) {
            wp_send_json_error(array('message' => 'Email sudah terdaftar dengan bisnis aktif'));
            return;
        }
        
        // Generate OTP
        $otp_code = sprintf('%06d', wp_rand(100000, 999999));
        $otp_expires = date('Y-m-d H:i:s', current_time('timestamp') + (15 * 60)); // 15 minutes
        
        // Get Google ID if logged in
        $google_id = '';
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $google_id = get_user_meta($current_user->ID, 'nsl-google-id', true);
            if (empty($google_id)) {
                $google_id = $current_user->ID; // Fallback to user ID
            }
        }
        
        // Insert registration data
        $result = $wpdb->insert(
            $wpdb->base_prefix . 'msme_registrations',
            array(
                'email' => $owner_email,
                'google_id' => $google_id,
                'business_name' => $business_name,
                'subdomain' => $subdomain,
                'business_category' => $business_category,
                'business_address' => $business_address,
                'phone_number' => $phone_number,
                'status' => 'pending',
                'otp_code' => $otp_code,
                'otp_expires' => $otp_expires,
                'created_date' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
            return;
        }
        
        // Send OTP email
        $email_sent = $this->send_otp_email($owner_email, $owner_name, $otp_code, $business_name);
        
        if (!$email_sent) {
            // Still return success but log the email issue
            error_log('MSME: OTP email failed to send to ' . $owner_email);
            wp_send_json_success(array(
                'message' => 'Registration successful but email sending failed. Please contact support.',
                'step' => 3,
                'email_status' => 'failed'
            ));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Registration successful! OTP sent to your email.',
            'step' => 3,
            'email_status' => 'sent',
            'otp_expires' => $otp_expires
        ));
    }
    
    /**
     * Handle OTP verification
     */
    public function verify_otp_code() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'msme_registration_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Validate required fields
        if (empty($_POST['otp_code']) || empty($_POST['email'])) {
            wp_send_json_error(array('message' => 'OTP code and email are required'));
            return;
        }
        
        $otp_code = sanitize_text_field($_POST['otp_code']);
        $email = sanitize_email($_POST['email']);
        
        // Validate OTP format (6 digits)
        if (!preg_match('/^\d{6}$/', $otp_code)) {
            wp_send_json_error(array('message' => 'Kode OTP harus 6 angka'));
            return;
        }
        
        global $wpdb;
        
        // Find registration with matching email and OTP
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->base_prefix}msme_registrations 
             WHERE email = %s AND otp_code = %s AND status = 'pending'",
            $email, $otp_code
        ));
        
        if (!$registration) {
            wp_send_json_error(array('message' => 'Kode OTP salah atau tidak ditemukan'));
            return;
        }
        
        // Check if OTP is expired
        $current_time = current_time('mysql');
        if (strtotime($current_time) > strtotime($registration->otp_expires)) {
            wp_send_json_error(array(
                'message' => 'Kode OTP sudah kedaluwarsa. Silakan minta kode baru.',
                'expired' => true
            ));
            return;
        }
        
        // Update registration status to verified
        $update_result = $wpdb->update(
            $wpdb->base_prefix . 'msme_registrations',
            array(
                'status' => 'verified',
                'otp_code' => null, // Clear OTP code for security
                'otp_expires' => null
            ),
            array('id' => $registration->id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($update_result === false) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
            return;
        }
        
        // Send notification to admin about new verified registration
        $this->notify_admin_new_registration($registration);
        
        wp_send_json_success(array(
            'message' => 'Verifikasi email berhasil! Pendaftaran Anda akan diproses oleh admin dalam 24 jam.',
            'status' => 'verified',
            'business_name' => $registration->business_name,
            'subdomain' => $registration->subdomain
        ));
    }
    
    /**
     * Send notification to admin about new verified registration
     */
    private function notify_admin_new_registration($registration) {
        $admin_email = get_site_option('admin_email');
        $subject = '[Cobalah.id] Pendaftaran Bisnis Baru Menunggu Persetujuan';
        
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2 style='color: #0073aa;'>Pendaftaran Bisnis Baru</h2>
            
            <p>Ada pendaftaran bisnis baru yang telah diverifikasi dan menunggu persetujuan:</p>
            
            <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                <tr style='background: #f8f9fa;'>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Nama Bisnis</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$registration->business_name}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Subdomain</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$registration->subdomain}.cobalah.id</td>
                </tr>
                <tr style='background: #f8f9fa;'>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Email</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$registration->email}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Kategori</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$registration->business_category}</td>
                </tr>
                <tr style='background: #f8f9fa;'>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Alamat</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$registration->business_address}</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Tanggal Daftar</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>{$registration->created_date}</td>
                </tr>
            </table>
            
            <p><strong>Silakan login ke admin panel untuk menyetujui atau menolak pendaftaran ini.</strong></p>
            
            <p>
                <a href='" . admin_url('network/admin.php?page=msme-business-manager') . "' 
                   style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                   Buka Admin Panel
                </a>
            </p>
            
            <hr style='margin: 30px 0;'>
            <p style='font-size: 12px; color: #666;'>
                Email otomatis dari Cobalah.id<br>
                Website Gratis untuk UMKM Indonesia
            </p>
        </body>
        </html>
        ";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Cobalah.id - Website Gratis untuk UMKM Indonesia <tidak-dibalas@cobalah.id>',
            'Reply-To: bantuan@cobalah.id'
        );
        
        wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Clean up expired and abandoned registrations
     */
    public function cleanup_expired_registrations($email = null) {
        global $wpdb;
        
        $current_time = current_time('mysql');
        
        if ($email) {
            // Clean up specific email's abandoned registrations
            $deleted = $wpdb->delete(
                $wpdb->base_prefix . 'msme_registrations',
                array(
                    'email' => $email,
                    'status' => 'pending'
                ),
                array('%s', '%s')
            );
            
            error_log("MSME: Cleaned up {$deleted} abandoned registration(s) for email: {$email}");
            return $deleted;
        } else {
            // Clean up all expired registrations (OTP expired + 1 hour grace period)
            $expired_time = date('Y-m-d H:i:s', current_time('timestamp') - (60 * 60)); // 1 hour ago
            
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->base_prefix}msme_registrations 
                 WHERE status = 'pending' 
                 AND (otp_expires < %s OR otp_expires IS NULL)
                 AND created_date < %s",
                $current_time,
                $expired_time
            ));
            
            error_log("MSME: Cleaned up {$deleted} expired registration(s)");
            return $deleted;
        }
    }
    
    /**
     * Check and clean up existing registration before allowing new one
     */
    private function prepare_clean_registration($email, $subdomain) {
        global $wpdb;
        
        // Check if user has any pending registrations
        $existing_registrations = $wpdb->get_results($wpdb->prepare(
            "SELECT id, subdomain, created_date, otp_expires FROM {$wpdb->base_prefix}msme_registrations 
             WHERE email = %s AND status = 'pending'",
            $email
        ));
        
        if ($existing_registrations) {
            // Clean up all pending registrations for this email
            $cleaned = $this->cleanup_expired_registrations($email);
            
            error_log("MSME: User {$email} had {$cleaned} pending registration(s), cleaned up for fresh start");
            return true;
        }
        
        // Also check if the specific subdomain exists in pending status
        $subdomain_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->base_prefix}msme_registrations 
             WHERE subdomain = %s AND status = 'pending'",
            $subdomain
        ));
        
        if ($subdomain_exists) {
            // Check if this is an expired registration
            $expired_subdomain = $wpdb->get_row($wpdb->prepare(
                "SELECT id, email, otp_expires FROM {$wpdb->base_prefix}msme_registrations 
                 WHERE subdomain = %s AND status = 'pending'",
                $subdomain
            ));
            
            if ($expired_subdomain) {
                $current_time = current_time('mysql');
                
                // If OTP expired or very old (1+ hour), clean it up
                if (!$expired_subdomain->otp_expires || 
                    strtotime($current_time) > strtotime($expired_subdomain->otp_expires) + (60 * 60)) {
                    
                    $wpdb->delete(
                        $wpdb->base_prefix . 'msme_registrations',
                        array('id' => $expired_subdomain->id),
                        array('%d')
                    );
                    
                    error_log("MSME: Cleaned up expired subdomain registration: {$subdomain}");
                    return true;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Manual cleanup via admin (AJAX)
     */
    public function manual_cleanup_registrations() {
        if (!current_user_can('manage_network')) {
            wp_die('Unauthorized');
        }
        
        $deleted = $this->cleanup_expired_registrations();
        echo '<div style="color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;">';
        echo '<strong>[✓] Cleanup Completed!</strong><br>';
        echo "Removed {$deleted} expired/abandoned registration(s).";
        echo '</div>';
        wp_die();
    }
    
    /**
     * Resend OTP code to user email
     */
    public function resend_otp_code() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'msme_registration_nonce')) {
            wp_send_json_error(array(
                'message' => 'Keamanan: Permintaan tidak valid. Silakan refresh halaman.'
            ));
        }
        
        $email = sanitize_email($_POST['email']);
        
        if (empty($email)) {
            wp_send_json_error(array(
                'message' => 'Email tidak valid.'
            ));
        }
        
        global $wpdb;
        
        // Get registration record
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->base_prefix}msme_registrations 
             WHERE email = %s AND status = 'pending'
             ORDER BY created_date DESC LIMIT 1",
            $email
        ));
        
        if (!$registration) {
            wp_send_json_error(array(
                'message' => 'Registrasi tidak ditemukan atau sudah diproses.'
            ));
        }
        
        // Rate limiting: Check if last OTP was sent within 1 minute
        if (!empty($registration->otp_expires)) {
            $last_otp_time = new DateTime($registration->otp_expires);
            $last_otp_time->modify('-15 minutes'); // OTP expires 15 min after creation
            $current_time = new DateTime();
            $time_diff = $current_time->getTimestamp() - $last_otp_time->getTimestamp();
            
            if ($time_diff < 60) { // Less than 1 minute ago
                $wait_seconds = 60 - $time_diff;
                wp_send_json_error(array(
                    'message' => "Tunggu {$wait_seconds} detik sebelum mengirim ulang kode verifikasi.",
                    'wait_time' => $wait_seconds
                ));
            }
        }
        
        // Generate new OTP
        $new_otp = sprintf('%06d', wp_rand(100000, 999999));
        $otp_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Update database with new OTP
        $update_result = $wpdb->update(
            $wpdb->base_prefix . 'msme_registrations',
            array(
                'otp_code' => $new_otp,
                'otp_expires' => $otp_expires
            ),
            array('id' => $registration->id)
        );
        
        if ($update_result === false) {
            wp_send_json_error(array(
                'message' => 'Gagal memperbarui kode OTP. Silakan coba lagi.'
            ));
        }
        
        // Send email with new OTP
        // CORRECT ORDER:
        $email_sent = $this->send_otp_email(
            $email, 
            $registration->owner_name, 
            $new_otp, 
            $registration->business_name
        );
        
        if (!$email_sent) {
            wp_send_json_error(array(
                'message' => 'Gagal mengirim email. Silakan coba lagi.'
            ));
        }
        
        // Success
        wp_send_json_success(array(
            'message' => 'Kode OTP baru telah dikirim ke email Anda. Periksa kotak masuk dan folder spam.',
            'sent' => true
        ));
    }
    
    /**
     * Registration Management Page (FIXED for actual database structure)
     */
    public function registration_management_page() {
        // Security check
        if (!current_user_can('manage_network')) {
            wp_die(__('Anda tidak memiliki izin untuk mengakses halaman ini.'));
        }
        
        global $wpdb;
        
        // Get current page for pagination
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        // Get filter parameters
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';
        
        // FIXED: Handle empty status as 'pending'
        $where_clause = "WHERE 1=1";
        if ($status_filter === 'pending') {
            $where_clause .= " AND (status = '' OR status = 'pending' OR status = 'verified')";
        } elseif ($status_filter !== 'all') {
            $where_clause .= $wpdb->prepare(" AND status = %s", $status_filter);
        }
        
        // Get registrations with correct column names
        $registrations_query = "
            SELECT * FROM {$wpdb->base_prefix}msme_registrations 
            $where_clause 
            ORDER BY created_date DESC 
            LIMIT $per_page OFFSET $offset
        ";
        $registrations = $wpdb->get_results($registrations_query);
        
        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM {$wpdb->base_prefix}msme_registrations $where_clause";
        $total_items = $wpdb->get_var($total_query);
        
        // Calculate pagination
        $total_pages = ceil($total_items / $per_page);
        
        // FIXED: Get statistics with empty status treated as pending
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN (status = '' OR status = 'pending' OR status = 'verified') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM {$wpdb->base_prefix}msme_registrations
        ");
        
        ?>
        <div class="wrap">
            <h1>Manajemen Pendaftaran Bisnis</h1>
            
            <!-- Filter Bar -->
            <div class="tablenav top">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="msme-registrations" />
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?php selected($status_filter, 'all'); ?>>Semua Status</option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>>Menunggu Persetujuan</option>
                        <option value="approved" <?php selected($status_filter, 'approved'); ?>>Disetujui</option>
                        <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>Ditolak</option>
                    </select>
                    <input type="submit" class="button" value="Filter" />
                </form>
                
                <!-- Statistics -->
                <div style="float: right;">
                    <strong>Statistik:</strong> 
                    Total: <?php echo $stats->total; ?> | 
                    Pending: <span style="color: orange;"><?php echo $stats->pending; ?></span> | 
                    Disetujui: <span style="color: green;"><?php echo $stats->approved; ?></span> | 
                    Ditolak: <span style="color: red;"><?php echo $stats->rejected; ?></span>
                </div>
                <div class="clear"></div>
            </div>
            
            <!-- Registrations Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 50px;">
                            <input type="checkbox" id="select-all-registrations" />
                        </th>
                        <th scope="col">Bisnis</th>
                        <th scope="col">Email</th>
                        <th scope="col">Kontak</th>
                        <th scope="col">Subdomain</th>
                        <th scope="col">Status</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registrations)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">
                                <em>Tidak ada pendaftaran ditemukan untuk status: <?php echo esc_html($status_filter); ?></em>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registrations as $registration): ?>
                            <tr data-registration-id="<?php echo $registration->id; ?>">
                                <td>
                                    <input type="checkbox" name="registration_ids[]" value="<?php echo $registration->id; ?>" />
                                </td>
                                <td>
                                    <strong><?php echo esc_html($registration->business_name); ?></strong><br>
                                    <small><?php echo esc_html($registration->business_category); ?></small>
                                </td>
                                <td>
                                    <?php echo esc_html($registration->email); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($registration->phone_number ?? 'N/A'); ?><br>
                                    <small><?php echo esc_html($registration->business_address ?? 'N/A'); ?></small>
                                </td>
                                <td>
                                    <code><?php echo esc_html($registration->subdomain); ?>.cobalah.id</code>
                                </td>
                                <td>
                                    <?php
                                    // FIXED: Handle empty status
                                    $display_status = $registration->status;
                                    if (empty($display_status)) {
                                        $display_status = 'pending';
                                    }
                                    
                                    $status_colors = [
                                        'pending' => 'orange',
                                        'verified' => 'orange',
                                        'approved' => 'green',
                                        'rejected' => 'red'
                                    ];
                                    $status_labels = [
                                        'pending' => 'Menunggu',
                                        'verified' => 'Menunggu',
                                        'approved' => 'Disetujui',
                                        'rejected' => 'Ditolak'
                                    ];
                                    $color = $status_colors[$display_status] ?? 'gray';
                                    $label = $status_labels[$display_status] ?? $display_status;
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                        <?php echo $label; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($registration->created_date)); ?>
                                </td>
                                <td>
                                    <?php if (empty($registration->status) || $registration->status === 'pending' || $registration->status === 'verified'): ?>
                                        <button type="button" class="button button-primary" 
                                                onclick="approveRegistration(<?php echo $registration->id; ?>)">
                                            Setujui
                                        </button>
                                        <button type="button" class="button" 
                                                onclick="rejectRegistration(<?php echo $registration->id; ?>)">
                                            Tolak
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="button" 
                                                onclick="viewRegistrationDetails(<?php echo $registration->id; ?>)">
                                            Lihat Detail
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Bulk Actions -->
            <div style="margin-top: 20px;">
                <select id="bulk-action">
                    <option value="">Pilih Aksi Massal</option>
                    <option value="approve">Setujui Terpilih</option>
                    <option value="reject">Tolak Terpilih</option>
                </select>
                <button type="button" class="button" onclick="executeBulkAction()">Jalankan</button>
            </div>
        </div>
        
        <style>
        .wrap table th, .wrap table td {
            padding: 8px 12px;
        }
        .wp-list-table tbody tr:hover {
            background-color: #f6f7f7;
        }
        </style>
        <?php
    }
    
}
// Initialize the plugin
MSME_Business_Manager::get_instance();

?>