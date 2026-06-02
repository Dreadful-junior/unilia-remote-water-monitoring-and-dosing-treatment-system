<?php
include 'db_connect.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $role = $_POST['role'];

    $sql = "UPDATE users SET role='$role' WHERE id=$user_id";

    if ($conn->query($sql) === TRUE) {
        $message = "User role updated successfully to '$role'!";
    } else {
        $message = "Error updating record: " . $conn->error;
    }
}

$sql = "SELECT id, fullname, email, role FROM users";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Manager Role Manager</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 2rem;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 800px;
            margin-bottom: 2rem;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .btn {
            padding: 5px 10px;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <h2>User Role Manager</h2>
    <?php if ($message): ?>
        <p style="color: green;"><?php echo $message; ?></p><?php endif; ?>

    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["id"] . "</td>";
                echo "<td>" . $row["fullname"] . "</td>";
                echo "<td>" . $row["email"] . "</td>";
                echo "<td>" . $row["role"] . "</td>";
                echo "<td>
                    <form method='POST' style='display:inline;'>
                        <input type='hidden' name='user_id' value='" . $row["id"] . "'>
                        <select name='role'>
                            <option value='technician' " . ($row['role'] == 'technician' ? 'selected' : '') . ">Technician</option>
                            <option value='manager' " . ($row['role'] == 'manager' ? 'selected' : '') . ">Manager</option>
                        </select>
                        <button type='submit' class='btn'>Update</button>
                    </form>
                </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No users found</td></tr>";
        }
        ?>
    </table>
    <p>Use this tool to set your account to 'Manager'. Afterwards, <b>Log Out</b> and <b>Log In</b> again to see the
        Manager
        Dashboard.</p>
</body>

</html>
