<?php
/**
 * Plugin Name: Bulk Delete WC Shipping Zones
 * Description: Bulk delete WooCommerce shipping zones with a clean table UI.
 * Version: 1.0
 * Author: Roxy Stoltz
 * Author URI: https://roxystoltz.com
 * Text Domain: bulk-delete-shipping-zones
 * License: GPLv2 or later
 */

add_action('admin_footer', function () {
    $screen = get_current_screen();
    if ($screen->id === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'shipping') {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                function addBulkDeleteUI() {
                    // Ensure we only add elements once
                    if ($('.zone-select').length === 0) {
                        // Count the existing columns
                        let colCount = $('.wc-shipping-zones thead tr th').length + 1; // Adding 1 for the checkbox column

                        // Insert "Select" column as the first column in the header
                        $('.wc-shipping-zones thead tr')
                            .prepend('<th class="wc-shipping-zone-select" style="width: 2%; min-width: 30px; text-align: center; padding: 5px;"><input type="checkbox" id="select-all-zones"></th>');

                        // Add checkboxes only inside <tbody> (user-defined zones), NOT inside <tfoot>
                        $('.wc-shipping-zone-rows tr[data-id]').each(function () {
                            let zoneId = $(this).attr('data-id');
                            if (zoneId && zoneId !== "0") { // Ensure "Rest of the World" (zone_id=0) is not affected
                                $(this).prepend('<td class="wc-shipping-zone-select" style="width: 2%; min-width: 30px; text-align: center; padding: 5px; vertical-align: middle;"><input type="checkbox" class="zone-select" data-zone-id="' + zoneId + '"></td>');
                            }
                        });

                        // Ensure "Rest of the World" row maintains correct column count
                        $('.wc-shipping-zone-worldwide tr').each(function () {
                            let currentCols = $(this).find('td').length;
                            if (currentCols < colCount) {
                                $(this).prepend('<td class="wc-shipping-zone-select"></td>'); // Add an empty cell at the start to match column count
                            }
                        });

                        // Add Bulk Delete button above the table
                        $('.wc-shipping-zones').before('<button class="button button-primary bulk-delete-zones" style="margin-bottom: 10px;">Delete Selected Zones</button>');
                    }
                }

                // Run function when the shipping zones table is fully loaded
                let checkZonesLoaded = setInterval(() => {
                    if ($('.wc-shipping-zone-rows tr[data-id]').length > 0) {
                        addBulkDeleteUI();
                        clearInterval(checkZonesLoaded);
                    }
                }, 500);

                // "Select All" functionality
                $(document).on('change', '#select-all-zones', function () {
                    $('.zone-select').prop('checked', $(this).prop('checked'));
                });

                // Handle Bulk Delete
                $(document).on('click', '.bulk-delete-zones', function (e) {
                    e.preventDefault();
                    let selectedZones = [];

                    $('.zone-select:checked').each(function () {
                        selectedZones.push($(this).data('zone-id'));
                    });

                    if (selectedZones.length > 0) {
                        if (confirm("Are you sure you want to delete the selected zones?")) {
                            selectedZones.forEach(function (zoneId) {
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'delete_woo_shipping_zone',
                                        zone_id: zoneId,
                                    },
                                    success: function (response) {
                                        if (response.success) {
                                            $('tr[data-id="' + zoneId + '"]').fadeOut();
                                        } else {
                                            alert('Failed to delete zone ID: ' + zoneId);
                                        }
                                    }
                                });
                            });
                        }
                    } else {
                        alert("No zones selected.");
                    }
                });
            });
        </script>
        <style>
            /* Ensure checkbox column is small and does not affect table width */
            .wc-shipping-zone-select {
                width: 2% !important;
                min-width: 30px;
                text-align: center;
            }

            /* Aligns all table columns properly */
            .wc-shipping-zones thead tr th,
            .wc-shipping-zones tbody tr td,
            .wc-shipping-zones tfoot tr td {
                vertical-align: middle;
            }
        </style>
        <?php
    }
});

add_action('wp_ajax_delete_woo_shipping_zone', function () {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized', 403);
    }

    if (!isset($_POST['zone_id'])) {
        wp_send_json_error('Invalid request', 400);
    }

    $zone_id = absint($_POST['zone_id']);
    if ($zone_id && $zone_id !== 0) { // Ensure "Rest of the World" zone is never deleted
        WC_Shipping_Zones::delete_zone($zone_id);
        wp_send_json_success('Zone deleted');
    }

    wp_send_json_error('Failed to delete', 500);
});
