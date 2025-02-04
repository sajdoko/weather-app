<?php
/*
Plugin Name: Weather App
Description: A plugin to display weather information for cities.
Version: 1.0
Author: Sajmir Doko
*/
if (!defined('ABSPATH')) exit; // Exit if accessed directly

function weather_app_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Create cities table
    $cities_table = $wpdb->prefix . "cities";

    // Drop if exists
    $wpdb->query("DROP TABLE IF EXISTS $cities_table");

    $sql = "CREATE TABLE $cities_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        city_name varchar(255) NOT NULL UNIQUE,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create weather_data table
    $weather_data_table = $wpdb->prefix . "weather_data";
    
    // Drop if exists
    $wpdb->query("DROP TABLE IF EXISTS $weather_data_table");
    
    $sql = "CREATE TABLE $weather_data_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        city_id mediumint(9) NOT NULL,
        temperature float NOT NULL,
        humidity float NOT NULL,
        wind_speed float NOT NULL,
        weather_description varchar(255) NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (city_id) REFERENCES $cities_table(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'weather_app_activate');

function weather_app_add_custom_role() {
    add_role(
        'weather_app_viewer',
        'Weather App Viewer',
        [
            'read' => true,
            'weather_app_view' => true,
        ]
    );
}
register_activation_hook(__FILE__, 'weather_app_add_custom_role');

function weather_app_remove_custom_role() {
    remove_role('weather_app_viewer');
}
register_deactivation_hook(__FILE__, 'weather_app_remove_custom_role');

function weather_app_shortcode($atts) {
  global $wpdb;

  // Fetch weather data from the database
  $table_name = $wpdb->prefix . "weather_data"; // Use the correct table name with prefix
  $cities_table = $wpdb->prefix . "cities";
  $results = $wpdb->get_results("
    SELECT wd.*, c.city_name 
    FROM $table_name wd 
    JOIN $cities_table c 
    ON wd.city_id = c.id 
    ORDER BY c.city_name, wd.timestamp DESC 
  ");

  // Output weather data
  $output = "<table class='weather-app-table'>";
  $output .= "<thead><tr><th>Temperature (°C)</th><th>Humidity (%)</th><th>Wind Speed (m/s)</th><th>Description</th><th>Timestamp</th></tr></thead>";
  $output .= "<tbody>";
  
  $current_city = '';
  foreach ($results as $row) {
      if ($current_city != $row->city_name) {
          $current_city = $row->city_name;
          $output .= '<tr><td colspan="7" style="text-align: center;background-color: #f4f4f4; font-weight: bold;">' . esc_html($current_city) . '</td></tr>';
      }
      $output .= "<tr>";
      $output .= "<td>" . esc_html($row->temperature) . "°C</td>";
      $output .= "<td>" . esc_html($row->humidity) . "%</td>";
      $output .= "<td>" . esc_html($row->wind_speed) . "m/s</td>";
      $output .= "<td>" . esc_html($row->weather_description) . "</td>";
      $output .= "<td>" . esc_html($row->timestamp) . "</td>";
      $output .= "</tr>";
  }

  $output .= "</tbody></table>";

  return $output;
}
add_shortcode('weather_app', 'weather_app_shortcode');

function weather_app_styles() {
  echo '<style>
      .weather-app-table {
          width: 100%;
          border-collapse: collapse;
          margin: 20px 0;
      }
      .weather-app-table th, .weather-app-table td {
          border: 1px solid #ddd;
          padding: 8px;
          text-align: center;
      }
      .weather-app-table th {
          background-color: #f4f4f4;
          font-weight: bold;
      }
  </style>';
}
add_action('wp_head', 'weather_app_styles');

if (!function_exists('weather_app_admin_page')) {
    function weather_app_admin_page() {
        global $wpdb;

        if (isset($_POST['action']) && $_POST['action'] == 'add_city') {
            $city_name = sanitize_text_field($_POST['city_name']);
            $existing_city = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . $wpdb->prefix . "cities WHERE city_name = %s", $city_name));
            if ($existing_city == 0) {
                $wpdb->insert(
                    $wpdb->prefix . "cities",
                    ['city_name' => $city_name]
                );
                echo "<div class='updated'><p>City added successfully!</p></div>";
            } else {
                echo "<div class='error'><p>City already exists!</p></div>";
            }
        }

        if (isset($_POST['action']) && $_POST['action'] == 'delete_weather_data') {
            $wd_id = intval($_POST['wd_id']);
            $wpdb->delete(
                $wpdb->prefix . "weather_data",
                ['id' => $wd_id]
            );
            // $wpdb->delete(
            //     $wpdb->prefix . "cities",
            //     ['id' => $wd_id]
            // );
            echo "<div class='updated'><p>City weather data deleted successfully!</p></div>";
        }

        if (isset($_POST['action']) && $_POST['action'] == 'sync_weather') {
            do_action('weather_app_fetch_data');
            echo "<div class='updated'><p>Weather data synced successfully!</p></div>";
        }

        echo '<div class="wrap"><h1>Weather App</h1>';
        echo '<form method="POST" action="">';
        echo '<input type="hidden" name="action" value="add_city">';
        echo '<table class="form-table">';
        echo '<tr><th>City Name:</th><td><input type="text" name="city_name" required><input type="submit" value="Add City" class="button button-primary"></td></tr>';
        echo '</table>';
        echo '</form>';

        echo '<form method="POST" action="" style="margin-top: 20px;">';
        echo '<input type="hidden" name="action" value="sync_weather">';
        echo '<p><input type="submit" value="Sync Weather Data" class="button button-secondary"></p>';
        echo '</form>';

        // Display added cities and weather data
        $cities = $wpdb->get_results("
            SELECT c.id, c.city_name, wd.id as wd_id, wd.temperature, wd.humidity, wd.wind_speed, wd.weather_description, wd.timestamp
            FROM " . $wpdb->prefix . "cities c
            LEFT JOIN " . $wpdb->prefix . "weather_data wd ON c.id = wd.city_id
            ORDER BY c.city_name, wd.timestamp DESC
        ");
        if ($cities) {
            echo '<h2>Added Cities and Weather Data</h2>';
            echo '<table class="widefat fixed" cellspacing="0">';
            echo '<thead><tr><th>City Name</th><th>Temperature (°C)</th><th>Humidity (%)</th><th>Wind Speed (m/s)</th><th>Description</th><th>Timestamp</th><th>Action</th></tr></thead>';
            echo '<tbody>';
            $current_city = '';
            foreach ($cities as $city) {
                if ($current_city != $city->city_name) {
                    $current_city = $city->city_name;
                    echo '<tr><td colspan="7" style="background-color: #f4f4f4; font-weight: bold;">' . esc_html($current_city) . '</td></tr>';
                }
                echo '<tr>';
                echo '<td>' . esc_html($city->city_name) . '</td>';
                echo '<td>' . esc_html($city->temperature) . '</td>';
                echo '<td>' . esc_html($city->humidity) . '</td>';
                echo '<td>' . esc_html($city->wind_speed) . '</td>';
                echo '<td>' . esc_html($city->weather_description) . '</td>';
                echo '<td>' . esc_html($city->timestamp) . '</td>';
                echo '<td>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="delete_weather_data">
                            <input type="hidden" name="wd_id" value="' . esc_attr($city->wd_id) . '">
                            <input type="submit" value="Delete" class="button button-danger">
                        </form>
                      </td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }
}

if (!function_exists('weather_app_menu')) {
    function weather_app_menu() {
        add_menu_page(
            'Weather App',
            'Weather App',
            'weather_app_view',
            'weather-app',
            'weather_app_admin_page',
            'dashicons-cloud',
            20
        );
    }
}
add_action('admin_menu', 'weather_app_menu');

function weather_app_remove_dashboard_and_profile_pages() {
    if (current_user_can('weather_app_view')) {
        remove_menu_page('index.php'); // Dashboard
        remove_menu_page('profile.php'); // Profile
    }
}
add_action('admin_menu', 'weather_app_remove_dashboard_and_profile_pages', 999);

function weather_app_schedule_cron() {
  if (!wp_next_scheduled('weather_app_fetch_data')) {
      wp_schedule_event(time(), 'hourly', 'weather_app_fetch_data');
  }
}
add_action('wp', 'weather_app_schedule_cron');

function weather_app_fetch_data() {
  global $wpdb;
  $api_key = 'f0e2ddb3bb9d159ee94d3b723d09ce03';
  $cities = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "cities");

  foreach ($cities as $city) {
      $api_url = "https://api.openweathermap.org/data/2.5/weather?q={$city->city_name}&appid={$api_key}&units=metric";
      $response = wp_remote_get($api_url);
      $data = json_decode(wp_remote_retrieve_body($response), true);

      if ($data) {
          $wpdb->insert(
              $wpdb->prefix . "weather_data",
              [
                  'city_id' => $city->id,
                  'temperature' => $data['main']['temp'],
                  'humidity' => $data['main']['humidity'],
                  'wind_speed' => $data['wind']['speed'],
                  'weather_description' => $data['weather'][0]['description']
              ]
          );
      }
  }
}
add_action('weather_app_fetch_data', 'weather_app_fetch_data');