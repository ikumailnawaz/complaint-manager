# 📱 WhatsApp Automation Microservice (wwebjs-service)

This lightweight Node.js service connects directly to WhatsApp Web to automate group and engineer dispatch messages from the Complaint Manager ERP.

---

## 🚀 How to Run Locally (Option A)

### 1. Install dependencies
```bash
cd wwebjs-service
npm install
```

### 2. Start the service
```bash
npm start
```
The server will start on `http://localhost:3000`.

### 3. Link your WhatsApp
* Open your browser to `http://localhost:3000` (or view the terminal).
* You will see a QR code.
* Open WhatsApp on your phone &rarr; **Settings** &rarr; **Linked Devices** &rarr; **Link a Device** &rarr; scan the QR code.
* Once scanned, the status will show **"Connected & Active"**.

### 4. Enable in Laravel `.env`
In your `complaint-manager/.env`:
```ini
WWEBJS_SERVICE_URL=http://localhost:3000
WWEBJS_SECRET=
WHATSAPP_FIELD_GROUP=1203630XXXXXXXX@g.us   # Optional: WhatsApp group ID
```

Now, clicking **"Notify on WhatsApp"** in Laravel will automatically send the message without touching your phone!

---

## 🌐 How to Deploy to Free Cloud (Option B: Railway / Render)

1. Create a free account on [Railway.app](https://railway.app) or [Render.com](https://render.com).
2. Deploy this `wwebjs-service` folder.
3. Open the public URL provided by Railway, scan the QR code once.
4. Copy the Railway URL into your Laravel `.env`:
   ```ini
   WWEBJS_SERVICE_URL=https://your-app.up.railway.app
   ```
