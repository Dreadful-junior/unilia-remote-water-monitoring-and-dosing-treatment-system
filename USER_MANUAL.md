# UniLi Remote Water Monitoring and Dosing Treatment System
## User Manual & Operation Guide

---

## Table of Contents
1. [System Overview](#system-overview)
2. [Getting Started](#getting-started)
3. [Dashboard Operations](#dashboard-operations)
4. [Treatment Control](#treatment-control)
5. [Water Quality Monitoring](#water-quality-monitoring)
6. [System Settings](#system-settings)
7. [User Roles & Permissions](#user-roles--permissions)
8. [Troubleshooting](#troubleshooting)
9. [Safety Guidelines](#safety-guidelines)

---

## System Overview

The UniLi Remote Water Monitoring and Dosing Treatment System is a comprehensive solution for monitoring water quality parameters and automatically dosing chemical treatments (Chlorine, Ozone, etc.) to maintain safe water quality standards.

### Key Features:
- **Real-time Water Quality Monitoring**: Tracks Turbidity, TDS (Total Dissolved Solids), Temperature, and Chlorine levels
- **Automated Chemical Dosing**: Automatic or manual pump control for chemical treatment
- **Remote Access**: Web-based interface accessible from any device
- **Hardware Integration**: ESP32 microcontroller for sensor reading and pump control
- **User Management**: Role-based access (Admin, Manager, User)
- **Alert System**: Notifications when water quality falls outside safe parameters
- **Historical Data**: Complete audit trail of all readings and treatments

---

## Getting Started

### 1. Logging In

1. Open your browser and navigate to the system URL
2. Enter your **Username** and **Password**
3. Click **"Login"**

**First-time Setup:**
- Contact your system administrator for login credentials
- Ensure your browser allows cookies (required for sessions)

### 2. Understanding the Dashboard

After logging in, you'll see the main **Dashboard** which displays:

- **Water Quality Readings**: Current values for Turbidity, TDS, Temperature, and Chlorine
- **System Status**: Whether the system is running, idle, or in maintenance
- **24-Hour Stability**: Whether water quality has remained within acceptable ranges
- **Recent Activities**: Log of recent treatments and changes
- **Hardware Status**: Connection status of sensors and pump controller

---

## Dashboard Operations

### Viewing Real-Time Readings

The dashboard displays four main water quality parameters:

| Parameter | Unit | Safe Range | Purpose |
|-----------|------|-----------|---------|
| **Turbidity** | NTU | < 5.0 | Measures water cloudiness |
| **TDS** | ppm | < 500 | Measures dissolved solids |
| **Temperature** | °C | 15-30 | Water temperature |
| **Chlorine** | ppm | 0.2-1.0 | Disinfection level |

### Understanding Status Indicators

- **🟢 Green**: Parameter within safe range
- **🟡 Yellow**: Parameter approaching warning threshold
- **🔴 Red**: Parameter outside safe range - action required

### Checking System Health

1. Look for the **"System Status"** indicator at the top
2. Check **"24-Hour Stability"** to see if water remained stable
3. Review **"Hardware Status"** to ensure ESP32 and sensors are connected

### Viewing Historical Data

1. Click **"Historical Data"** from the menu
2. Select date range you want to analyze
3. View charts showing trends over time
4. Export data as CSV if needed

---

## Treatment Control

### Accessing Treatment Control

1. From the main menu, select **"Treatment"**
2. You'll see the Treatment Control Panel

### Operating Modes

The system has two operational modes:

#### **Auto Mode** (Automatic Dosing)
- System automatically monitors water quality
- Triggers pump when readings exceed thresholds
- Runs for a preset duration at specified intervals
- **Best for**: Routine operations with minimal intervention

**To enable Auto Mode:**
1. Click the **"Auto Mode"** button
2. Set parameters:
   - **Run Duration**: How long the pump runs (in seconds)
   - **Interval**: Time between treatment cycles (in minutes)
   - **Max Daily Dose**: Maximum chemical allowable per day (in ml)
3. Click **"Activate Auto Mode"**

#### **Manual Mode** (Direct Control)
- Operator controls pump manually
- Useful for emergency situations or maintenance
- Requires Admin or Manager privileges

**To operate in Manual Mode:**
1. Click the **"Manual Mode"** button
2. Use **"START PUMP"** button to begin dosing
3. Use **"STOP PUMP"** button to end treatment
4. Monitor the treatment progress in real-time

### Changing Chemical Type

1. Go to **Settings > Treatment Settings**
2. Select the active chemical from dropdown:
   - Chlorine
   - Ozone
   - Other configured chemicals
3. Each chemical has different pump flow rates and dosing schedules
4. Click **"Save"** to apply changes

### Treatment History

View all past treatments:
1. Click **"Treatment History"** tab in Treatment Control
2. See timestamp, duration, chemical used, and volume dosed
3. View system mode during each treatment

---

## Water Quality Monitoring

### Setting Quality Thresholds

Only **Managers** and **Admins** can modify thresholds:

1. Go to **Settings > Monitoring Settings**
2. Set maximum acceptable values:
   - **Max Turbidity**: Default 5.0 NTU
   - **Max TDS**: Default 500 ppm
   - **Max Temperature**: Default 30°C
3. Click **"Update Thresholds"**

### Understanding Alerts

Alerts are triggered when readings exceed thresholds:

**Alert Types:**
- **Critical (Red)**: Immediate action required
- **Warning (Yellow)**: Monitor closely
- **Info (Blue)**: Routine notification

**Alert Actions:**
1. Read the alert message
2. Check current readings on Dashboard
3. If Critical:
   - Activate Manual Mode to treat water immediately
   - Contact system administrator if problem persists
4. If Warning:
   - Monitor readings every 15 minutes
   - Consider triggering treatment early

### Viewing Alerts

1. Click **"Alerts"** in the main menu
2. See active and recent alerts
3. Click alert to view detailed information
4. Mark as read or acknowledge

---

## System Settings

### Accessing Settings (Manager/Admin Only)

Click **"Settings"** in the main menu to access:

#### **1. Monitoring Settings**
- Set water quality thresholds
- Configure alert sensitivity
- Adjust monitoring intervals

#### **2. Treatment Settings**
- Configure pump flow rate (ml/min)
- Set dosing duration limits
- Define auto-mode intervals
- Set daily maximum dose limits

#### **3. Hardware Settings**
- View ESP32 controller status
- Configure sensor calibration
- Set communication parameters

#### **4. User Management**
- Add new users
- Assign user roles
- Manage permissions
- Reset passwords

#### **5. System Settings**
- Configure timezone
- Set system name
- Manage maintenance schedules
- Configure email notifications

#### **6. Alert Settings**
- Enable/disable specific alerts
- Configure alert thresholds
- Set notification recipients

---

## User Roles & Permissions

### Role-Based Access Control

#### **Administrator**
- **Can do:** Everything
- **Access:** All features, settings, and user management
- **Responsibility:** System maintenance and security

#### **Manager**
- **Can do:** Monitor, operate treatments, configure settings
- **Access:** Dashboard, Treatment Control, Settings (limited)
- **Responsibility:** Day-to-day operations and treatment decisions

#### **Operator**
- **Can do:** Monitor water quality, view reports
- **Access:** Dashboard, Historical Data, Reports
- **Cannot do:** Change settings, operate treatments
- **Responsibility:** Data observation and reporting

#### **User (View Only)**
- **Can do:** View dashboard and readings only
- **Access:** Dashboard (read-only)
- **Cannot do:** Any operational actions
- **Responsibility:** Awareness and monitoring

---

## Troubleshooting

### Common Issues and Solutions

#### Issue: "ESP32 Disconnected"
**Cause:** Hardware not connected or communication lost
**Solution:**
1. Check physical USB connection
2. Verify ESP32 power supply
3. Restart the ESP32 device
4. Reload the webpage
5. Contact IT support if problem persists

#### Issue: Readings Not Updating
**Cause:** Sensor malfunction or data transmission failure
**Solution:**
1. Refresh the dashboard (F5)
2. Check sensor connections
3. Verify ESP32 status
4. Try calibrating sensors
5. Restart system if needed

#### Issue: Pump Won't Start in Manual Mode
**Cause:** 
- Hardware not recognized
- Daily dose limit exceeded
- Emergency stop activated
**Solution:**
1. Verify hardware status shows green
2. Check daily dose total vs. limit
3. Look for emergency alerts
4. Try Auto Mode instead
5. Contact administrator

#### Issue: Extremely High/Low Readings
**Cause:** Sensor calibration error or malfunction
**Solution:**
1. Note the unusual reading
2. Take manual water sample for verification
3. If reading is wrong: Calibrate sensor
4. If reading is correct: Alert is working properly

#### Issue: Can't Access Settings
**Cause:** Your user role doesn't have permission
**Solution:**
1. Ask your Manager or Administrator
2. Verify your account role in system
3. Request role upgrade if needed

### Getting Help

If you encounter issues:
1. **Take a screenshot** of the problem
2. **Note the time** it occurred
3. **Check for error messages** at top of page
4. **Contact your system administrator** with details

---

## Safety Guidelines

### Critical Do's and Don'ts

#### ✅ DO:
- Check water quality readings daily
- Monitor alerts and respond promptly
- Keep chemical tanks filled and accessible
- Log out after each session
- Report any hardware malfunctions immediately
- Maintain calibration schedules
- Keep emergency contact information accessible
- Use appropriate Personal Protective Equipment (PPE) when handling chemicals

#### ❌ DON'T:
- Override safety thresholds without authorization
- Leave the system unattended during treatment
- Ignore critical alerts
- Use expired chemicals
- Attempt hardware repairs yourself
- Share login credentials
- Disable alerts without understanding consequences
- Operate the system beyond its designed capacity

### Emergency Procedures

**If Water Quality Becomes Critical:**

1. **STOP** - Click "Emergency Stop" if available
2. **ALERT** - Notify your supervisor immediately
3. **TREAT** - Manually activate treatment if safe
4. **CHECK** - Verify readings stabilize
5. **REPORT** - Document what happened and time

**If Pump Malfunctions:**

1. Switch to Manual Mode
2. Try to operate manually
3. If still not working:
   - Stop all operations
   - Contact maintenance team
   - Do NOT attempt to repair yourself

---

## Maintenance Schedule

### Daily Tasks
- ✓ Check dashboard readings
- ✓ Review alerts
- ✓ Verify treatment history

### Weekly Tasks
- ✓ Calibrate sensors
- ✓ Check chemical levels
- ✓ Review historical data trends
- ✓ Verify backup pumps

### Monthly Tasks
- ✓ Full system check
- ✓ Test emergency procedures
- ✓ Review all user activity logs
- ✓ Update threshold settings if needed

### Quarterly Tasks
- ✓ Hardware inspection
- ✓ Sensor replacement
- ✓ Software updates
- ✓ Backup database

---

## Technical Support Contact

For technical assistance:

- **Email:** support@unili.system
- **Phone:** +1-XXX-XXX-XXXX
- **Available:** Monday-Friday, 8AM-5PM
- **Emergency:** Contact on-call technician (24/7)

---

## Glossary

- **Turbidity**: Measure of water cloudiness (NTU = Nephelometric Turbidity Units)
- **TDS**: Total Dissolved Solids - concentration of dissolved substances (ppm = parts per million)
- **Chlorine**: Chemical disinfectant for water treatment
- **NTU**: Nephelometric Turbidity Unit
- **ESP32**: Microcontroller managing sensors and pump
- **PPE**: Personal Protective Equipment
- **Threshold**: Set limit for acceptable parameter values
- **Calibration**: Process of adjusting sensors for accuracy

---

**Version:** 1.0  
**Last Updated:** June 2, 2026  
**Document Status:** APPROVED

For questions or manual updates, contact your system administrator.
