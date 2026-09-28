# 🎓 STI College Centralized Exam Timer System (CETS)

![Version](https://img.shields.io/badge/Version-1.2.9-00b35c?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-Production-fDD000?style=for-the-badge&logoColor=003666)
![PHP](https://img.shields.io/badge/PHP-7.4%20%7C%208.x-003666?style=for-the-badge&logo=php)

A smart, centralized, and automated examination timer and monitoring system built tailored for STI College Campuses. Developed by **Team 5:02 Studio**, this system aims to streamline exam schedules, monitor classroom displays in real-time, and ensure seamless OTA (Over-The-Air) deployments across multiple branch servers.

---

## ✨ Key Features

* 📺 **MCR Telemetry (Master Control Room)**: Real-time classroom monitoring, remote commands, and smart room-collision prevention.
* ⚡ **Plug & Play Deployment**: Easily install via GitHub releases or a standalone web installer.
* 🔄 **OTA (Over-The-Air) Updates**: Built-in 1-click update mechanism (`updater.php` & `patch.php`). Branch servers can automatically detect new versions, download the update package, and extract it seamlessly without breaking local database configurations.
* 📅 **Smart Schedule Management**: 
  * Mass upload via CSV.
  * Real-time Excel-like Grid Batch Encoder.
  * Manual single-entry scheduling.
* 🔍 **Live Deep Search**: Instantly filter the exam queue by Section, Room, Day, Subject, or Proctor with dynamic counts.
* 🔒 **Proctor Security**: Dedicated proctor passwords to securely unlock TV displays during examination periods.
* 🎨 **Clean Light Mode Aesthetic**: Clean, responsive, and branded UI using the official STI blue and yellow color palette.

---

## 🛠️ System Architecture

The ecosystem relies on a Master-Branch architecture:
1. **Master Server (5:02 Studio Cloud)**: Hosts the centralized Update Portal (`patch.php`), tracking `check.json`, and the deployment packages.
2. **Campus Branch (Local/Production Server)**: Runs the actual timer system, database, and telemetry endpoints. Connects to the master server solely for firmware updates.

---

## 🚀 Installation Guide (For Branch Campuses)

### Option A: via Auto-Installer (Recommended)
This is the standard and most stable way to install the system on your campus server.

1. Go to the [Repo](https://github.com/AllenSTICubao/STICentralizedExamTimerSystem/).
2. Download [502STICets-installer.php](https://github.com/AllenSTICubao/STICentralizedExamTimerSystem/blob/main/502STICets-installer.php)
3. Extract the contents directly into your web server's root directory (e.g., `htdocs`, `public_html`, or `/var/www/html`).
4. Open your browser and navigate to the setup wizard:
   ```text
   http://your-domain.com/502STICets-installer.php

 ### Option B: Manual Installation via GitHub
This is for when you want a certain release or patch.

1. Go to the [Releases Page](https://github.com/AllenSTICubao/STICentralizedExamTimerSystem/releases).
2. Download the `.zip` file of the latest stable version.
3. Extract the contents directly into your web server's root directory (e.g., `htdocs`, `public_html`, or `/var/www/html`).
4. Open your browser and navigate to the setup wizard:
   ```text
   http://your-domain.com/syssetup.php


 
