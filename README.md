# GitHub Activity Dashboard 🚀

A highly customizable, theme-based open-source dashboard to track and display your daily GitHub activities, commit history, and active repositories in real-time. Built with pure PHP and Vanilla JavaScript, ensuring lightweight performance with zero heavy framework dependencies.

## 🌟 Features

- **6 Unique Built-in Themes:** Instantly change the entire look and feel of the dashboard by modifying a single line in your `.env` file!
  1. `jarvis` - Sci-Fi Holographic HUD (Default)
  2. `activity_monitor` - macOS Style Data Tables
  3. `nitro_hud` - Luxury Sports Car Digital Instrument Cluster
  4. `hacker_terminal` - Retro MS-DOS / Matrix Terminal with Typewriter effects
  5. `minimal_light` - Clean, Corporate, Apple/Notion style Light Mode
  6. `glass_light` - Apple Vision Pro style Glassmorphism with animated Mesh Gradients
- **Real-Time GitHub Data:** Uses the official GitHub REST API to fetch your latest pushes, commits, and repository activities.
- **Smart Caching Engine:** Built-in 10-minute caching mechanism (`cache.json`) to prevent hitting GitHub's API rate limits.
- **Privacy Controls:** Easily hide specific system logs or active project names via environment variables.
- **Extensible Theme Engine:** Create your own completely custom HTML/CSS structures inside the `themes/` directory!

## 🚀 Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/lufi3x/gitActivityDashboard.git
   cd gitActivityDashboard
   ```

2. **Environment Configuration:**
   Copy the example environment file and create your own `.env` file.
   ```bash
   cp .env.example .env
   ```

3. **Get a GitHub Personal Access Token:**
   - Go to your GitHub Settings -> Developer settings -> Personal access tokens -> Tokens (classic).
   - Generate a new token with at least `repo` and `read:user` permissions.

4. **Update `.env` file:**
   Open the `.env` file and insert your GitHub username and the token you just created.
   ```env
   GITHUB_USERNAME=your_github_username
   GITHUB_TOKEN=ghp_your_personal_access_token_here
   
   UI_THEME=jarvis
   ```

5. **Start a local PHP server:**
   You can use PHP's built-in web server to run the project instantly:
   ```bash
   php -S localhost:8000
   ```
   Open `http://localhost:8000` in your browser.

## 🎨 Changing Themes

This dashboard uses a powerful Theme Engine. To change the active theme, simply open your `.env` file and modify the `UI_THEME` variable.

```env
# Available Options:
# jarvis
# activity_monitor 
# nitro_hud
# hacker_terminal
# minimal_light
# glass_light

UI_THEME=nitro_hud
```
*Note: The `jarvis` theme also supports color customization via the `DEFAULT_THEME` variable (Options: `theme-cyan`, `theme-orange`, `theme-red`, `theme-green`, `theme-purple`).*

## 🛠️ Privacy Settings

You can hide sensitive data if you intend to host this dashboard publicly (e.g., on a portfolio site or a TV monitor in your office). 

Edit these flags in your `.env` file:
```env
# Set to false to hide the detailed action logs
SHOW_SYSTEM_LOGS=true

# Set to false to hide the names of the repositories you are working on
SHOW_ACTIVE_PROJECTS=true
```

## 📝 License

This project is open-sourced software licensed under the [MIT license](LICENSE).
