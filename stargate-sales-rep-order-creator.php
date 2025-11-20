<?php
/*
Plugin Name: Stargate Sales Rep Order Creator
Description: Sales rep login, order creation form, and dashboard for WooCommerce. Includes auto-created pages, rep role, rep-access control, and mobile sales app origin tagging.
Version: 2.2.0
Author: Chris Mortlock
Text Domain: stargate-sales-rep-orders
*/

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------
 * CREATE SALES REP ROLE & REQUIRED PAGES ON ACTIVATION
 * ------------------------------------------------------- */
register_activation_hook(__FILE__, function () {

    // Create sales rep role
    add_role(
        'sales_rep',
        'Sales Rep',
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => false
        )
    );

    // Auto-create pages
    $pages = array(
        array('Rep Login', 'rep-login', '[sales_rep_login]'),
        array('Rep Dashboard', 'rep-dashboard', '[sales_rep_dashboard]'),
        array('Sell Tickets', 'sell', '[sales_rep_order_form]')
    );

    foreach ($pages as $page) {
        list($title, $slug, $content) = $page;

        $exists = get_page_by_path($slug);
        if ($exists) continue;

        wp_insert_post(array(
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page'
        ));
    }
});

/* -------------------------------------------------------
 * MAIN CLASS
 * ------------------------------------------------------- */
class Stargate_Sales_Rep_Order_Creator
{
    protected $message = '';
    protected $message_type = '';

    public function __construct()
    {
        add_action('init', array($this, 'handle_form_submission'));
        add_shortcode('sales_rep_order_form', array($this, 'render_form'));
        add_shortcode('sales_rep_dashboard', array($this, 'render_dashboard'));
        add_shortcode('sales_rep_login', array($this, 'render_login_form'));
        add_action('admin_init', array($this, 'block_admin_access'));
    }

    /* -------------------------------------------------------
     * BLOCK SALES REPS FROM WP-ADMIN
     * ------------------------------------------------------- */
    public function block_admin_access()
    {
        if (current_user_can('sales_rep') && !wp_doing_ajax()) {
            wp_redirect(site_url('/rep-dashboard'));
            exit;
        }
    }

    /* -------------------------------------------------------
     * CHECK WOO
     * ------------------------------------------------------- */
    protected function is_woocommerce_active()
    {
        return class_exists('WooCommerce');
    }

    /* -------------------------------------------------------
     * HANDLE ORDER CREATION
     * ------------------------------------------------------- */
    public function handle_form_submission()
    {
        if (!isset($_POST['sroc_submit']) || !isset($_POST['sroc_action']) || $_POST['sroc_action'] !== 'create_order') {
            return;
        }

        if (!isset($_POST['sroc_nonce']) || !wp_verify_nonce($_POST['sroc_nonce'], 'sroc_create_order')) {
            $this->message = 'Security error.';
            $this->message_type = 'error';
            return;
        }

        if (!is_user_logged_in()) {
            wp_redirect(site_url('/rep-login'));
            exit;
        }

        if (!$this->is_woocommerce_active()) {
            $this->message = 'WooCommerce not active.';
            $this->message_type = 'error';
            return;
        }

        // Auto-fill Rep ID
        $current_user = wp_get_current_user();
        $rep_id = $current_user->user_login;

        $product_id     = absint($_POST['sroc_product_id']);
        $customer_name  = sanitize_text_field($_POST['sroc_customer_name']);
        $customer_email = sanitize_email($_POST['sroc_customer_email']);
        $quantity       = absint($_POST['sroc_quantity']);

        if (!$product_id || !$customer_name || !is_email($customer_email)) {
            $this->message = 'All fields required.';
            $this->message_type = 'error';
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            $this->message = 'Product not found.';
            $this->message_type = 'error';
            return;
        }

        try {

            // Create the order
            $order = wc_create_order();

            // Tag order origin
            $order->set_created_via('mobile_sales_app');

            $order->add_product($product, $quantity);

            $order->set_billing_first_name($customer_name);
            $order->set_billing_email($customer_email);

            $order->set_payment_method('cod');
            $order->set_payment_method_title('Cash collected by sales rep');

            $order->calculate_totals();

            $order_id = $order->get_id();

            update_post_meta($order_id, '_sales_rep_id', $rep_id);

            $order->add_order_note("Order created via Mobile Sales App by rep: $rep_id");
            $order->update_status('processing', 'Sales rep order created.');

            $this->message = "Order created successfully: #" . $order->get_order_number();
            $this->message_type = 'success';

        } catch (Exception $e) {

            $this->message = 'Error: ' . $e->getMessage();
            $this->message_type = 'error';
        }
    }

    /* -------------------------------------------------------
     * PRODUCT LOADER
     * ------------------------------------------------------- */
    protected function get_products()
    {
        return wc_get_products(array(
            'status' => 'publish',
            'limit'  => -1,
            'orderby'=> 'title',
            'order'  => 'ASC'
        ));
    }

    /* -------------------------------------------------------
     * SALES REP ORDER FORM (/sell)
     * ------------------------------------------------------- */
    public function render_form()
    {
        if (!is_user_logged_in()) {
            wp_redirect(site_url('/rep-login'));
            exit;
        }

        if (!(current_user_can('sales_rep') || current_user_can('administrator'))) {
            return '<p>You do not have permission to access this page.</p>';
        }

        $products = $this->get_products();

        ob_start();
        ?>
        <div class="sroc-wrapper">

            <?php if ($this->message): ?>
                <div class="sroc-message sroc-message-<?php echo $this->message_type; ?>">
                    <?php echo wp_kses_post($this->message); ?>
                </div>
            <?php endif; ?>

            <h2>Create New Ticket Order</h2>

            <form method="post">
                <?php wp_nonce_field('sroc_create_order', 'sroc_nonce'); ?>
                <input type="hidden" name="sroc_action" value="create_order">

                <p>
                    <label>Product</label>
                    <select name="sroc_product_id" required>
                        <option value="">Select product…</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p->get_id(); ?>">
                                <?php echo esc_html($p->get_name()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <p>
                    <label>Quantity</label>
                    <input type="number" name="sroc_quantity" min="1" value="1" required>
                </p>

                <p>
                    <label>Customer Name</label>
                    <input type="text" name="sroc_customer_name" required>
                </p>

                <p>
                    <label>Customer Email</label>
                    <input type="email" name="sroc_customer_email" required>
                </p>

                <button class="button button-primary" name="sroc_submit">Create Order</button>

            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /* -------------------------------------------------------
     * SALES REP DASHBOARD (/rep-dashboard)
     * ------------------------------------------------------- */
    public function render_dashboard()
    {
        if (!is_user_logged_in()) {
            wp_redirect(site_url('/rep-login'));
            exit;
        }

        $user = wp_get_current_user();
        $rep_id = $user->user_login;

        $orders = wc_get_orders(array(
            'limit' => -1,
            'meta_key' => '_sales_rep_id',
            'meta_value' => $rep_id,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        ob_start();
        ?>
        <h2>Your Ticket Sales</h2>
        <p><strong>Rep ID:</strong> <?php echo esc_html($rep_id); ?></p>

        <?php if (empty($orders)): ?>
            <p>No orders yet.</p>
        <?php else: ?>

            <table class="sroc-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order->get_order_number(); ?></td>
                        <td><?php echo $order->get_date_created()->date('d M Y H:i'); ?></td>
                        <td><?php echo esc_html($order->get_billing_first_name()); ?></td>
                        <td><?php echo esc_html($order->get_billing_email()); ?></td>
                        <td>
                            <?php foreach ($order->get_items() as $item): ?>
                                <?php echo esc_html($item->get_name()); ?> × <?php echo $item->get_quantity(); ?><br>
                            <?php endforeach; ?>
                        </td>
                        <td><?php echo wc_price($order->get_total()); ?></td>
                        <td><?php echo wc_get_order_status_name($order->get_status()); ?></td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>

        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /* -------------------------------------------------------
     * SALES REP LOGIN PAGE (/rep-login)
     * ------------------------------------------------------- */
    public function render_login_form()
    {
        if (is_user_logged_in()) {
            wp_redirect(site_url('/rep-dashboard'));
            exit;
        }

        $msg = '';

        if (isset($_POST['sroc_login'])) {

            $creds = array(
                'user_login'    => sanitize_text_field($_POST['sroc_username']),
                'user_password' => $_POST['sroc_password'],
                'remember'      => true
            );

            $user = wp_signon($creds, false);

            if (is_wp_error($user)) {
                $msg = '<div class="sroc-login-error">Invalid username or password.</div>';
            } else {
                wp_redirect(site_url('/rep-dashboard'));
                exit;
            }
        }

        ob_start();
        ?>
        <div class="sroc-login-wrapper">
            <h2>Sales Rep Login</h2>

            <?php echo $msg; ?>

            <form method="post">

                <p>
                    <label>Username</label>
                    <input type="text" name="sroc_username" required>
                </p>

                <p>
                    <label>Password</label>
                    <input type="password" name="sroc_password" required>
                </p>

                <button class="button button-primary" name="sroc_login">Login</button>

            </form>
        </div>

        <style>
            .sroc-login-wrapper {
                max-width: 400px;
                margin: 40px auto;
                padding: 25px;
                border: 1px solid #ddd;
                background: #fafafa;
                border-radius: 4px;
            }
            .sroc-login-error {
                background: #ffe6e6;
                border: 1px solid #dc3232;
                padding: 10px;
                margin-bottom: 15px;
                border-radius: 3px;
            }
            .sroc-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            .sroc-table th, .sroc-table td {
                padding: 8px;
                border: 1px solid #ddd;
            }
            .sroc-table th {
                background: #f5f5f5;
            }
        </style>
        <?php
        return ob_get_clean();
    }
}

/* INIT PLUGIN */
new Stargate_Sales_Rep_Order_Creator();
