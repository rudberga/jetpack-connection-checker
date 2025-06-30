# Jetpack Connection Checker

A simple diagnostic plugin for self-hosted WordPress sites using Jetpack. This tool adds a section under **Tools > Jetpack Connection** that helps site owners verify if Jetpack is properly connected and communicating with its infrastructure.

🛠️ **Main Features**

- Checks Jetpack connection status (active, partial, or broken)
- Detects common issues like blocked XML-RPC or REST API
- Warns if the site is likely a staging site
- Provides detailed diagnostic logs
- Built with user-friendliness in mind (no coding required)

🔴🟡🟢 Visual indicators help users quickly assess the connection state.

---

🚧 **Still Under Development**

This is an early release and not fully tested in production environments. Many improvements are planned or in progress, such as:

- Better error explanations and recovery suggestions
- More precise handling of staging vs. production status
- Optional debug logging and export

---

📦 **Installation**

1. Download the latest `.zip` file from the [Releases](../../releases) section.
2. Upload the ZIP via **Plugins > Add New** in your WordPress admin.
3. Activate the plugin.
4. Go to **Tools > Jetpack Connection** to run the diagnostic.
