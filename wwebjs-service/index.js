/**
 * WhatsApp Web Automation Microservice for Bank Complaint Manager ERP
 * Powered by whatsapp-web.js & Express
 */

const express = require('express');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcodeTerminal = require('qrcode-terminal');
const QRCode = require('qrcode');
const cors = require('cors');
const puppeteer = require('puppeteer');

const app = express();
// Increase JSON payload limit to 10MB (for base64 email screenshots)
app.use(express.json({ limit: '10mb' }));
app.use(cors());

const PORT = process.env.PORT || 3000;
const AUTH_SECRET = process.env.WWEBJS_SECRET || '';

let qrCodeData = null;
let isReady = false;
let statusText = 'Initializing Chromium...';

// Get the Chromium path that puppeteer downloaded
const executablePath = puppeteer.executablePath();
console.log('[wwebjs-service] Using Chromium at:', executablePath);

// Initialize WhatsApp client
const client = new Client({
    authStrategy: new LocalAuth({ dataPath: './.wwebjs_auth' }),
    puppeteer: {
        executablePath: executablePath,
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-accelerated-2d-canvas',
            '--no-first-run',
            '--no-zygote',
            '--disable-gpu',
            '--disable-extensions',
            '--disable-background-networking',
        ]
    }
});

client.on('qr', (qr) => {
    qrCodeData = qr;
    isReady = false;
    statusText = 'Waiting for QR scan...';
    console.log('\n[WhatsApp] Scan the QR code below in your WhatsApp app (Linked Devices):\n');
    qrcodeTerminal.generate(qr, { small: true });
    console.log('\nOr open http://localhost:' + PORT + ' in your browser to scan.\n');
});

client.on('ready', () => {
    isReady = true;
    qrCodeData = null;
    statusText = 'Connected & Ready';
    console.log('\n✅ [WhatsApp] Bot successfully connected & ready to dispatch messages!\n');
});

client.on('authenticated', () => {
    statusText = 'Authenticated — loading session...';
    console.log('[WhatsApp] Session authenticated. Loading chats...');
});

client.on('auth_failure', (msg) => {
    statusText = 'Auth failed: ' + msg;
    console.error('[WhatsApp] Authentication failed:', msg);
});

client.on('loading_screen', (percent, message) => {
    statusText = `Loading WhatsApp (${percent}%): ${message}`;
    console.log(`[WhatsApp] Loading: ${percent}% - ${message}`);
});

client.on('disconnected', (reason) => {
    console.warn('[WhatsApp] Disconnected:', reason);
    isReady = false;
    statusText = 'Disconnected — reconnecting...';
    setTimeout(() => client.initialize(), 3000);
});

client.initialize();


// Status endpoint & Web QR View
app.get('/', async (req, res) => {
    if (isReady) {
        return res.send(`
            <div style="font-family:sans-serif; text-align:center; padding:50px;">
                <h1 style="color:#059669;">✅ WhatsApp Bot is Connected & Active!</h1>
                <p>Status: Ready to receive dispatch requests from Laravel ERP.</p>
                <p style="color:#6b7280; font-size:12px;">Go back to your ERP ticket and click "Send to WhatsApp Group Now".</p>
            </div>
        `);
    }

    if (qrCodeData) {
        const qrImage = await QRCode.toDataURL(qrCodeData);
        return res.send(`
            <div style="font-family:sans-serif; text-align:center; padding:40px; background:#f9fafb; min-height:100vh;">
                <h2 style="color:#1f2937;">📱 Scan QR Code to Link WhatsApp</h2>
                <p style="color:#374151;">Open WhatsApp on your phone &rarr; <strong>Settings / Menu</strong> &rarr; <strong>Linked Devices</strong> &rarr; <strong>Link a Device</strong></p>
                <img src="${qrImage}" style="width:300px; height:300px; border:2px solid #d1d5db; padding:12px; border-radius:16px; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,0.1);" />
                <p style="color:#6b7280; font-size:12px; margin-top:16px;">QR code expires in ~20 seconds. Page auto-refreshes every 5s.</p>
                <script>setTimeout(() => location.reload(), 5000);</script>
            </div>
        `);
    }

    res.send(`
        <div style="font-family:sans-serif; text-align:center; padding:50px; background:#f9fafb; min-height:100vh;">
            <h2 style="color:#1f2937;">⏳ WhatsApp Bot Initializing...</h2>
            <p style="color:#374151; font-size:16px; margin:20px 0;">
                <strong>Status:</strong> ${statusText}
            </p>
            <div style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; max-width:400px; margin:0 auto;">
                <p style="color:#6b7280; font-size:13px;">Chromium is starting up. This takes <strong>15–30 seconds</strong> on first run.</p>
                <p style="color:#6b7280; font-size:13px;">QR code will appear here automatically.</p>
            </div>
            <script>setTimeout(() => location.reload(), 3000);</script>
        </div>
    `);
});

// Send message endpoint (supports text + optional image_base64)
app.post('/send', async (req, res) => {
    // Secret check if configured
    if (AUTH_SECRET) {
        const authHeader = req.headers.authorization;
        if (!authHeader || authHeader !== `Bearer ${AUTH_SECRET}`) {
            return res.status(401).json({ error: 'Unauthorized secret token' });
        }
    }

    if (!isReady) {
        return res.status(503).json({ error: 'WhatsApp bot is not linked or not ready. Please scan QR code first.' });
    }

    const { phone, group_id, message, image_base64, image_filename } = req.body;

    if (!message && !image_base64) {
        return res.status(400).json({ error: 'Missing message or image_base64 parameter.' });
    }

    try {
        let chatId = null;

        // If target is a group
        if (group_id) {
            chatId = group_id.includes('@g.us') ? group_id : `${group_id}@g.us`;
        } else if (phone) {
            // Clean phone to international format (e.g. 03001234567 -> 923001234567)
            let cleaned = phone.replace(/[^0-9]/g, '');
            if (cleaned.startsWith('0')) {
                cleaned = '92' + cleaned.substring(1);
            }
            chatId = `${cleaned}@c.us`;
        }

        if (!chatId) {
            return res.status(400).json({ error: 'Please provide either a phone number or group_id.' });
        }

        let lastSent = null;

        // 1. Send the image first (if provided)
        if (image_base64) {
            const { MessageMedia } = require('whatsapp-web.js');
            // Strip data URL prefix if present (e.g. "data:image/png;base64,...")
            const base64Data = image_base64.replace(/^data:image\/\w+;base64,/, '');
            const media = new MessageMedia('image/png', base64Data, image_filename || 'complaint-email.png');
            lastSent = await client.sendMessage(chatId, media, { caption: message || '' });
        }

        // 2. Send text message separately if no image (or as additional message)
        if (!image_base64 && message) {
            lastSent = await client.sendMessage(chatId, message);
        }

        return res.json({
            success: true,
            message_id: lastSent?.id?._serialized || ('wamid.' + Date.now()),
            timestamp: lastSent?.timestamp || Math.floor(Date.now() / 1000),
        });
    } catch (err) {
        console.error('[WhatsApp Send Error]:', err);
        return res.status(500).json({ error: err.message || 'Failed to dispatch message' });
    }
});

// Status check endpoint (used by Laravel to verify bot is ready)
app.get('/status', (req, res) => {
    res.json({ ready: isReady, qr_pending: !!qrCodeData });
});


app.listen(PORT, () => {
    console.log(`[wwebjs-service] HTTP Server running on http://localhost:${PORT}`);
});
