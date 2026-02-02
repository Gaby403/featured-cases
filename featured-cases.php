<?php
/**
 * Plugin Name: Featured Cases
 * Description: Featured Cases plugin with CPT, admin management page, shortcode and Gutenberg block.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

class Featured_Cases_Plugin {

    const CPT = 'featured_case';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . self::CPT, [$this, 'save_meta']);
        add_action('admin_menu', [$this, 'register_admin_page']);

        // Shortcode
        add_shortcode('featured_cases', [$this, 'render_featured_cases']);

        // Gutenberg Block (dynamic)
        add_action('init', [$this, 'register_block']);
    }

    /* =========================
     * CPT
     * ========================= */
    public function register_cpt() {
        register_post_type(self::CPT, [
            'labels' => [
                'name'          => 'Featured Cases',
                'singular_name' => 'Featured Case',
                'add_new_item'  => 'Add New Case',
                'edit_item'     => 'Edit Case',
            ],
            'public'       => true,
            'menu_icon'    => 'dashicons-portfolio',
            'supports'     => ['title'],
            'show_in_rest' => true,
        ]);
    }

    /* =========================
     * META BOXES
     * ========================= */
    public function register_meta_boxes() {
        add_meta_box(
            'fc_case_details',
            'Case Details',
            [$this, 'render_meta_box'],
            self::CPT,
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        wp_nonce_field('fc_save_case', 'fc_nonce');

        $case_type  = get_post_meta($post->ID, '_fc_case_type', true);
        $settlement = get_post_meta($post->ID, '_fc_settlement_amount', true);
        ?>

        <table class="form-table">
            <tr>
                <th>
                    <label for="fc_case_type">Case Type</label>
                </th>
                <td>
                    <input
                        type="text"
                        id="fc_case_type"
                        name="fc_case_type"
                        class="regular-text"
                        value="<?php echo esc_attr($case_type); ?>"
                        placeholder="e.g. Car Accident, Work Injury"
                    >
                </td>
            </tr>

            <tr>
                <th>
                    <label for="fc_settlement_amount">Settlement Amount</label>
                </th>
                <td>
                    <input
                        type="text"
                        id="fc_settlement_amount"
                        name="fc_settlement_amount"
                        class="regular-text"
                        value="<?php echo esc_attr($settlement); ?>"
                        placeholder="e.g. $25,000"
                    >
                </td>
            </tr>
        </table>

        <?php
    }

    public function save_meta($post_id) {
        if (
            !isset($_POST['fc_nonce']) ||
            !wp_verify_nonce($_POST['fc_nonce'], 'fc_save_case')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['fc_case_type'])) {
            update_post_meta(
                $post_id,
                '_fc_case_type',
                sanitize_text_field($_POST['fc_case_type'])
            );
        }

        if (isset($_POST['fc_settlement_amount'])) {
            update_post_meta(
                $post_id,
                '_fc_settlement_amount',
                sanitize_text_field($_POST['fc_settlement_amount'])
            );
        }
    }

    /* =========================
     * ADMIN PAGE
     * ========================= */
    public function register_admin_page() {
        add_menu_page(
            'Featured Cases',
            'Featured Cases',
            'manage_options',
            'fc-manage-cases',
            [$this, 'render_admin_page'],
            'dashicons-portfolio',
            20
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $cases = get_posts([
            'post_type'      => self::CPT,
            'posts_per_page' => -1,
        ]);
        ?>

        <div class="wrap">
            <h1>Manage Featured Cases</h1>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Case Type</th>
                        <th>Settlement Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($cases) : ?>
                        <?php foreach ($cases as $case) : ?>
                            <tr>
                                <td><?php echo esc_html($case->post_title); ?></td>
                                <td><?php echo esc_html(get_post_meta($case->ID, '_fc_case_type', true)); ?></td>
                                <td><?php echo esc_html(get_post_meta($case->ID, '_fc_settlement_amount', true)); ?></td>
                                <td>
                                    <a href="<?php echo get_edit_post_link($case->ID); ?>" class="button">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4">No featured cases found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p style="margin-top:15px;">
                <a href="<?php echo admin_url('post-new.php?post_type=' . self::CPT); ?>"
                   class="button button-primary">
                    Add New Case
                </a>
            </p>
        </div>

        <?php
    }

    /* =========================
     * SHORTCODE + BLOCK RENDER
     * ========================= */
    public function render_featured_cases($atts = []) {
        $query = new WP_Query([
            'post_type'      => self::CPT,
            'posts_per_page' => 3,
        ]);

        if (!$query->have_posts()) {
            return '<p>No featured cases found.</p>';
        }

        ob_start();
        ?>
        <div class="featured-cases">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <div class="featured-case">
                    <h3><?php the_title(); ?></h3>
                    <p>
                        <strong>Case Type:</strong>
                        <?php echo esc_html(get_post_meta(get_the_ID(), '_fc_case_type', true)); ?>
                    </p>
                    <p>
                        <strong>Settlement Amount:</strong>
                        <?php echo esc_html(get_post_meta(get_the_ID(), '_fc_settlement_amount', true)); ?>
                    </p>
                </div>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();

        return ob_get_clean();
    }

    /* =========================
     * GUTENBERG BLOCK
     * ========================= */
    public function register_block() {
        register_block_type(
            __DIR__ . '/blocks/featured-cases',
            [
                'render_callback' => [$this, 'render_featured_cases'],
            ]
        );
    }
}

new Featured_Cases_Plugin();
