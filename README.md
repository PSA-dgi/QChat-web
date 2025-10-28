# QChat-web 💬

A lightweight, **single-file** web chat room built with **PHP**, **HTML**, **CSS**, and **JavaScript** — zero heavy dependencies, easy to deploy.

---

## 🚀 Features

- Real-time chat updates (polling with AJAX)  
- File uploads (images, audio, video, PDF, etc.)  
- Uses **SQLite** — self-contained database  
- Timestamps + usernames  
- “Clear Chat” button  
- Responsive dark UI (mobile & desktop)  
- Auto “seen” marking for new messages  
- Minimal footprint — just one PHP + frontend code  

---

## 🧩 Structure

The repo contains:

- `QChat-web.PHP` — main PHP file that handles routing (send, fetch, download, clear, seen)  
- `README.md` — you’re looking at it  
- (If present) `uploads/` folder for file storage  
- (If present) `chat.sqlite` — the SQLite database  

---

## 🛠 Installation

1. Clone this repo:
   ```bash
   git clone https://github.com/PSA-dgi/QChat-web.git
