<?php
/*
Plugin Name: Custom Contact Form
Description: A plugin to display a contact form using a shortcode, store submissions, and manage entries in the admin area. Use this shortcode for display form [custom_contact_form]
Version: 1.3
Author: Pradeep Prajapati
Author URI: https://pradeepprajapat.netlify.app/
*/

// Hook to initialize the plugin
register_activation_hook(__FILE__, 'custom_contact_form_create_table');
register_deactivation_hook(__FILE__, 'custom_contact_form_delete_table');

// Create database table for storing inquiries
function custom_contact_form_create_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'contact_form_inquiries';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        message text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY unique_email (email)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Hook to delete the table on plugin deactivation
function custom_contact_form_delete_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'contact_form_inquiries';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

// Shortcode to display the contact form
add_shortcode('custom_contact_form', 'custom_contact_form_display');

function custom_contact_form_display()
{
    $message = '';

    if (isset($_POST['contact_form_submit'])) {
        $message = custom_contact_form_handle_submission();
    }

    ob_start();
?>
    <style>
        .contact-form-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 30px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .contact-form-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .contact-form-container input,
        .contact-form-container textarea {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
        }

        .contact-form-container textarea {
            resize: vertical;
            height: 120px;
        }

        .contact-form-container input[type="submit"] {
            width: auto;
            padding: 10px 20px;
            background-color: #0073aa;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: block;
            margin: 0 auto;
        }

        .contact-form-container input[type="submit"]:hover {
            background-color: #005177;
        }

        .contact-form-message {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            font-size: 16px;
            text-align: center;
        }

        .contact-form-message.success {
            background-color: #d4edda;
            color: #155724;
        }

        .contact-form-message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>

    <form method="post" action="" class="contact-form-container">
        <label for="contact_form_name">Name:</label>
        <input type="text" name="contact_form_name" id="contact_form_name" required>

        <label for="contact_form_email">Email:</label>
        <input type="email" name="contact_form_email" id="contact_form_email" required>

        <label for="contact_form_message">Message:</label>
        <textarea name="contact_form_message" id="contact_form_message" required></textarea>

        <input type="submit" name="contact_form_submit" value="Submit">

        <?php if ($message) : ?>
            <div class="contact-form-message <?php echo $message['status']; ?>">
                <?php echo $message['text']; ?>
            </div>
        <?php endif; ?>
    </form>

    <script>
        // Hide success message after 30 seconds
        setTimeout(function() {
            var messageElement = document.querySelector('.contact-form-message');
            if (messageElement) {
                messageElement.style.display = 'none';
            }
        }, 10000);
    </script>
    <?php
    return ob_get_clean();
}

// Handle form submission
function custom_contact_form_handle_submission()
{
    if (isset($_POST['contact_form_submit'])) {
        global $wpdb;
        $name = sanitize_text_field($_POST['contact_form_name']);
        $email = sanitize_email($_POST['contact_form_email']);
        $message = sanitize_textarea_field($_POST['contact_form_message']);

        // Validate email
        if (!is_email($email)) {
            return [
                'status' => 'error',
                'text' => 'Invalid email address. Please try again.'
            ];
        }

        // Check if the email already exists
        $existing_entry = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}contact_form_inquiries WHERE email = %s", $email));

        if ($existing_entry > 0) {
            return [
                'status' => 'error',
                'text' => 'This email has already been used to submit an inquiry.'
            ];
        }

        // Save entry to the database
        $wpdb->insert(
            $wpdb->prefix . 'contact_form_inquiries',
            [
                'name' => $name,
                'email' => $email,
                'message' => $message
            ]
        );

        // Send email using PHPMailer
        require_once(plugin_dir_path(__FILE__) . 'PHPMailer/src/PHPMailer.php');
        require_once(plugin_dir_path(__FILE__) . 'PHPMailer/src/SMTP.php');
        require_once(plugin_dir_path(__FILE__) . 'PHPMailer/src/Exception.php');

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'pradeeplaptop66@gmail.com';
        $mail->Password = 'hmzmgyzlcfobpynq';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('pradeeplaptop66@gmail.com', 'Contact Form');
        $mail->addAddress('pradeepprajapati3369@gmail.com'); // Add Reciver email here

        $mail->Subject = 'New from data ' . $name;
        $mail->Body    = "Name: $name\nEmail: $email\n\nMessage:\n$message";

        if (!$mail->send()) {
            return [
                'status' => 'error',
                'text' => 'Mailer Error: ' . $mail->ErrorInfo
            ];
        }

        return [
            'status' => 'success',
            'text' => 'Your form was submitted successfully.'
        ];
    }
}

// Admin menu for viewing inquiries
add_action('admin_menu', 'custom_contact_form_admin_menu');

function custom_contact_form_admin_menu()
{
    add_menu_page(
        'Contact Form Inquiries',
        'All Form Data',
        'manage_options',
        'custom_contact_form_inquiries',
        'custom_contact_form_display_inquiries'
    );
}

// Handle CSV export
if (isset($_GET['export_csv'])) {
    custom_contact_form_export_csv();
}

function custom_contact_form_display_inquiries()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'contact_form_inquiries';

    // Handle delete functionality
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $wpdb->delete($table_name, ['id' => $id]);
        echo "<script>window.location='?page=custom_contact_form_inquiries';</script>";
    }

    // Handle edit functionality
    if (isset($_POST['update_inquiry'])) {
        $id = intval($_POST['id']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $message = sanitize_textarea_field($_POST['message']);

        $wpdb->update(
            $table_name,
            [
                'name' => $name,
                'email' => $email,
                'message' => $message
            ],
            ['id' => $id]
        );
        echo "<script>window.location='?page=custom_contact_form_inquiries';</script>";
    }

    // Display inquiries
    echo '<h1>Contact Form Data</h1>';
    echo '<a href="?page=custom_contact_form_inquiries&export_csv=1" class="button button-primary">Export to CSV</a>';

    echo '<style>
        .contact-form-inquiries-table {
            width: 90%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .contact-form-inquiries-table th, .contact-form-inquiries-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .contact-form-inquiries-table th {
            background-color: #0073aa;
            color: white;
            text-transform: uppercase;
        }
        .contact-form-inquiries-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .contact-form-inquiries-table tr:hover {
            background-color: #f1f1f1;
        }
        .action-buttons a {
            padding: 8px 12px;
            margin-right: 5px;
            text-decoration: none;
            color: white;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .action-buttons .edit-btn {
            background-color: #28a745; /* Green for Edit */
        }
        .action-buttons .edit-btn:hover {
            background-color: #218838;
        }
        .action-buttons .delete-btn {
            background-color: #dc3545; /* Red for Delete */
        }
        .action-buttons .delete-btn:hover {
            background-color: #c82333;
        }
    </style>';

    $inquiries = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<table class="contact-form-inquiries-table">';
    echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Message</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    foreach ($inquiries as $inquiry) {
        echo "<tr>
            <td>{$inquiry->id}</td>
            <td>{$inquiry->name}</td>
            <td>{$inquiry->email}</td>
            <td>{$inquiry->message}</td>
            <td class='action-buttons'>
                <a href='?page=custom_contact_form_inquiries&delete={$inquiry->id}' class='delete-btn' onclick='return confirm(\"Are you sure you want to delete this entry?\")'>Delete</a>
                <a href='?page=custom_contact_form_inquiries&edit={$inquiry->id}' class='edit-btn'>Edit</a>
            </td>
        </tr>";
    }
    echo '</tbody>';
    echo '</table>';

    // Handle edit form
    if (isset($_GET['edit'])) {
        $id = intval($_GET['edit']);
        $inquiry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    ?>
        <h2>Edit Data</h2>
        <style>
            .edit-form {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            .edit-form label {
                display: block;
                margin-bottom: 8px;
                font-weight: bold;
            }

            .edit-form input,
            .edit-form textarea {
                width: 100%;
                padding: 12px;
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
            }

            .edit-form textarea {
                height: 150px;
                resize: vertical;
            }

            .edit-form .button-wrapper {
                text-align: center;
                /* Center the button */
            }

            .edit-form input[type="submit"] {
                background-color: #0073aa;
                color: #fff;
                border: none;
                padding: 12px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                transition: background-color 0.3s ease;
                display: inline-block;
                /* Fit the button to its content */
            }

            .edit-form input[type="submit"]:hover {
                background-color: #005177;
            }

            .edit-form input[type="submit"]:focus {
                outline: none;
                box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
            }
        </style>
        <form method="post" class="edit-form">
            <input type="hidden" name="id" value="<?php echo esc_attr($inquiry->id); ?>">
            <label for="name">Name:</label>
            <input type="text" name="name" value="<?php echo esc_attr($inquiry->name); ?>" required>

            <label for="email">Email:</label>
            <input type="email" name="email" value="<?php echo esc_attr($inquiry->email); ?>" required>

            <label for="message">Message:</label>
            <textarea name="message" required><?php echo esc_textarea($inquiry->message); ?></textarea>

            <div class="button-wrapper">
                <input type="submit" name="update_inquiry" value="Update">
            </div>
        </form>
<?php
    }
}

// Function to export inquiries to CSV
function custom_contact_form_export_csv()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'contact_form_inquiries';

    // Fetch all data from the database
    $inquiries = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    // Set the CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="contact_form_data.csv"');

    // Output the CSV column headings
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Message']);

    // Output the data in CSV format
    foreach ($inquiries as $inquiry) {
        fputcsv($output, [
            $inquiry['id'],
            $inquiry['name'],
            $inquiry['email'],
            $inquiry['message'],

        ]);
    }
    fclose($output);
    exit();
}
