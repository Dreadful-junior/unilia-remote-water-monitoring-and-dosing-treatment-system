# UniLi Remote Water Monitoring and Dosing Treatment System

A comprehensive web-based solution for real-time water quality monitoring and automated chemical dosing treatment. Built with PHP, MySQL, and ESP32 integration for complete remote water management.

## 🌊 Features

- **Real-time Water Quality Monitoring**
  - Turbidity (NTU)
  - TDS - Total Dissolved Solids (ppm)
  - Temperature (°C)
  - Chlorine Levels (ppm)

- **Automated Chemical Dosing**
  - Auto mode: Scheduled treatments based on water quality
  - Manual mode: Direct operator control
  - Multiple chemical support (Chlorine, Ozone, etc.)
  - Safety limits and daily dose tracking

- **Remote Access**
  - Web-based dashboard
  - Real-time data visualization
  - Mobile-responsive design

- **Hardware Integration**
  - ESP32 microcontroller communication
  - Sensor data acquisition
  - Pump control via relay

- **User Management**
  - Role-based access control (Admin, Manager, Operator, User)
  - Secure authentication
  - User activity logging

- **Alerts & Notifications**
  - Critical, warning, and info alerts
  - Email notifications
  - Alert history tracking

- **Data Management**
  - Historical data storage
  - CSV export capability
  - Audit trails
  - Performance analytics

## 📋 System Requirements

### Server
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled
- XAMPP recommended for development

### Hardware
- ESP32 Development Board
- Water quality sensors (Turbidity, TDS, Temperature, Chlorine)
- Peristaltic pump with relay control

### Browser
- Chrome, Firefox, Safari, or Edge (latest versions)
- JavaScript enabled
- Cookies enabled

## 🚀 Quick Start

### 1. Installation

```bash
# Clone the repository
git clone https://github.com/Dreadful-junior/unilia-remote-water-monitoring-and-dosing-treatment-system.git

# Navigate to project directory
cd unilia-remote-water-monitoring-and-dosing-treatment-system

# Copy to XAMPP
xcopy . "C:\xampp\htdocs\water system" /E /I
```

### 2. Database Setup

```bash
# Import the database
mysql -u root -p < water_system_manual_export_20260505_155642.sql

# Or use phpMyAdmin
# 1. Open http://localhost/phpmyadmin
# 2. Create new database: water_system
# 3. Import SQL file
```

### 3. Configuration

Edit `db_connect.php` with your database credentials:

```php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "water_system";
```

### 4. First Access

1. Start XAMPP (Apache & MySQL)
2. Open `http://localhost/water%20system/`
3. Default login credentials:
   - Username: `admin`
   - Password: `admin` (change immediately)

## 📚 Documentation

### User Manual
See [USER_MANUAL.md](USER_MANUAL.md) for complete operational guide including:
- System overview
- Dashboard operations
- Treatment control procedures
- Water quality monitoring
- Settings configuration
- Troubleshooting guide
- Safety guidelines

### Directory Structure

```
water-system/
├── api/                    # REST API endpoints
├── assets/                 # CSS, JavaScript, images
│   ├── css/               # Stylesheets
│   ├── js/                # Client-side JavaScript
│   └── img/               # Images and logos
├── includes/              # Reusable PHP components
├── config/                # Configuration files
├── logs/                  # System logs
├── uploads/               # User uploads (avatars, etc.)
├── reports/               # Generated reports
├── scratch/               # Development/testing files
├── dashboard.php          # Main dashboard
├── treatment.php          # Treatment control
├── settings.php           # System settings
├── login.php              # Authentication
├── db_connect.php         # Database connection
└── USER_MANUAL.md         # User documentation
```

## 🎯 Core Modules

### Dashboard (`dashboard.php`)
- Real-time water quality readings
- System status and health indicators
- 24-hour stability overview
- Recent activity log
- Alert notifications

### Treatment Control (`treatment.php`)
- Auto/Manual mode switching
- Pump control interface
- Treatment history
- Chemical type selection
- Real-time monitoring during treatment

### Settings
- **Monitoring Settings**: Water quality thresholds
- **Treatment Settings**: Pump parameters and limits
- **Hardware Settings**: ESP32 and sensor configuration
- **User Management**: Create and manage users
- **System Settings**: General configuration
- **Alert Settings**: Notification preferences

### Reports (`reports.php`)
- Water quality reports
- Treatment history analysis
- Compliance documentation
- CSV export functionality

## 🔐 Security Features

- Password hashing with salt
- SQL injection prevention
- CSRF protection
- Session management
- Role-based access control
- User activity logging
- Secure database connection

## 🛠️ ESP32 Firmware

Arduino sketches included for ESP32 integration:
- `esp32_institutional_final.ino` - Production firmware
- `ESP32_FINAL.ino` - Development version
- Reads sensor data
- Controls pump relay
- Communicates with web server

### ESP32 Setup
1. Install Arduino IDE
2. Add ESP32 board support
3. Install required libraries (WiFi, SPIFFS, etc.)
4. Update WiFi credentials in firmware
5. Upload to ESP32 board
6. Configure API endpoint in web app

## 📊 Database Schema

Key tables:
- `users` - User accounts and authentication
- `sensor_data` - Water quality readings
- `hardware_settings` - System configuration
- `treatment_settings` - Dosing parameters
- `monitoring_settings` - Alert thresholds
- `monitoring_logs` - Treatment history
- `alerts` - Alert records
- `hardware_recognition` - Hardware status

## 🚨 Usage Scenarios

### Scenario 1: Automatic Daily Treatment
1. System monitors water continuously
2. When turbidity exceeds 5.0 NTU, auto-treats
3. Runs pump for 60 seconds every 30 minutes
4. Logs treatment history
5. Sends alert notification to manager

### Scenario 2: Emergency Manual Treatment
1. Operator sees critical alert
2. Switches to Manual Mode
3. Manually activates pump
4. Monitors readings in real-time
5. Stops pump when readings normalize

### Scenario 3: Weekly Compliance Report
1. Manager generates report for previous week
2. Downloads CSV with all readings
3. Verifies regulatory compliance
4. Archives documentation

## 🔧 Troubleshooting

### Common Issues

**ESP32 Disconnected**
- Check USB cable connection
- Verify power supply to ESP32
- Restart ESP32 device
- Check firewall settings

**Readings Not Updating**
- Verify sensor connections
- Calibrate sensors
- Check ESP32 status
- Restart application

**Pump Won't Start**
- Verify hardware status
- Check daily dose limit
- Look for emergency alerts
- Verify user permissions

See [USER_MANUAL.md](USER_MANUAL.md#troubleshooting) for detailed troubleshooting.

## 📝 Default Credentials

⚠️ **IMPORTANT: Change these immediately after first login**

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | admin |
| Manager | manager | manager |
| Operator | operator | operator |

## 🔄 API Endpoints

- `GET /api/latest.php` - Get latest sensor data
- `GET /api/historical_data.php` - Get historical readings
- `POST /api/toggle_pump.php` - Control pump
- `GET /api/analytics.php` - Get analytics data
- `POST /api/receive.php` - Receive data from ESP32

## 📱 Responsive Design

- Dashboard adapts to mobile, tablet, and desktop
- Touch-friendly controls
- Optimized performance
- Progressive Web App ready

## 🤝 Contributing

Contributions welcome! Please:
1. Fork the repository
2. Create feature branch (`git checkout -b feature/NewFeature`)
3. Commit changes (`git commit -m 'Add NewFeature'`)
4. Push to branch (`git push origin feature/NewFeature`)
5. Open Pull Request

## 📄 License

This project is proprietary. For licensing inquiries, contact the development team.

## 👥 Support

- **Documentation**: See [USER_MANUAL.md](USER_MANUAL.md)
- **Issues**: Report via GitHub Issues
- **Email**: support@unili.system
- **Hours**: Monday-Friday, 8AM-5PM

## 🎓 Project Information

- **Institution**: UniLi
- **Purpose**: Water Quality Monitoring and Treatment
- **Version**: 1.0
- **Last Updated**: June 2, 2026
- **Status**: Production Ready

## 🏗️ Architecture

```
┌─────────────────┐
│  Web Browser    │
│   Dashboard     │
└────────┬────────┘
         │ HTTP/S
         ▼
┌─────────────────────────┐
│   PHP Web Server        │
│  (Apache + XAMPP)       │
│ ├─ Authentication       │
│ ├─ Dashboard Logic      │
│ ├─ Treatment Control    │
│ └─ API Endpoints        │
└────────┬────────────────┘
         │ SQL
         ▼
┌─────────────────┐
│    MySQL DB     │
│  Sensor Data    │
│  User Accounts  │
│  Settings       │
└────────┬────────┘
         │ TCP/IP
         ▼
┌─────────────────┐
│  ESP32 Board    │
│  ├─ WiFi Module │
│  ├─ Sensors     │
│  └─ Pump Relay  │
└─────────────────┘
```

---

**Ready to use?** Read the [USER_MANUAL.md](USER_MANUAL.md) to get started!

