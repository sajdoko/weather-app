<?php
/*
Plugin Name: Weather App
Description: A plugin to display weather information for cities.
Version: 1.0
Author: Sajmir Doko
*/
if (!defined('ABSPATH')) exit; // Exit if accessed directly


function weather_app_shortcode($atts) {
  global $wpdb;

  // Fetch weather data from the database
  $table_name = $wpdb->prefix . "weather_data"; // Use the correct table name with prefix
  $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 100");

  // Output weather data
  $output = "<table class='weather-app-table'>";
  $output .= "<thead><tr><th>City</th><th>Temperature (°C)</th><th>Humidity (%)</th><th>Wind Speed (m/s)</th><th>Description</th><th>Timestamp</th></tr></thead>";
  $output .= "<tbody>";

  foreach ($results as $row) {
      $output .= "<tr>";
      $output .= "<td>" . esc_html($row->city_name) . "</td>";
      $output .= "<td>" . esc_html($row->temperature) . "</td>";
      $output .= "<td>" . esc_html($row->humidity) . "</td>";
      $output .= "<td>" . esc_html($row->wind_speed) . "</td>";
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

function weather_app_menu() {
  add_menu_page(
      'Weather App',
      'Weather App',
      'manage_options',
      'weather-app',
      'weather_app_admin_page',
      'dashicons-cloud',
      20
  );
}
add_action('admin_menu', 'weather_app_menu');

function weather_app_admin_page() {
  global $wpdb;

  if ($_POST['action'] == 'add_city') {
      $city_name = sanitize_text_field($_POST['city_name']);
      $api_key = sanitize_text_field($_POST['api_key']);
      $wpdb->insert(
          $wpdb->prefix . "cities",
          ['city_name' => $city_name, 'api_key' => $api_key]
      );
      echo "<div class='updated'><p>City added successfully!</p></div>";
  }

  echo '<div class="wrap"><h1>Weather App</h1>';
  echo '<form method="POST" action="">';
  echo '<input type="hidden" name="action" value="add_city">';
  echo '<table class="form-table">';
  echo '<tr><th>City Name:</th><td><input type="text" name="city_name" required></td></tr>';
  echo '<tr><th>API Key:</th><td><input type="text" name="api_key" required></td></tr>';
  echo '</table>';
  echo '<p><input type="submit" value="Add City" class="button button-primary"></p>';
  echo '</form></div>';
}

function weather_app_schedule_cron() {
  if (!wp_next_scheduled('weather_app_fetch_data')) {
      wp_schedule_event(time(), 'hourly', 'weather_app_fetch_data');
  }
}
add_action('wp', 'weather_app_schedule_cron');

function weather_app_fetch_data() {
  global $wpdb;
  $cities = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "cities");

  foreach ($cities as $city) {
      $api_url = "https://api.openweathermap.org/data/2.5/weather?q={$city->city_name}&appid={$city->api_key}&units=metric";
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
