import express from "express";
import { DatabaseSync } from "node:sqlite";
import crypto from "node:crypto";

function hashPassword(password) {
    const salt = crypto.randomBytes(16).toString("hex");
    const hash = crypto.scryptSync(password, salt, 64).toString("hex");
    return `${salt}:${hash}`;
}

function verifyPassword(password, store) {
    const [salt, storedHash] = stored.split(":");
    const candidate = crypto.scryptSync(password, salt, 64);
    return crypto.timingSafeEqual(candidate, Buffer.from(storedHash, "hex"));
}


export function createApp(dbPath = "todo.sqlite") {
    const db = new DatabaseSync(dbPath);
    db.exec(`
        PRAGMA foreign_keys ON; 
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            text TEXT NOT NULL,
            done INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS sessions (
            token_digest TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id),
            expires_at INTEGER NOT NULL
        );
    `);

    const app = express();
    app.use(express.json({ limit: "32kb" }));

    app.post("/api/register", (req, res) => {
        const { email, password } = req.body;

        const passwordHash = hashPassword(password);
        const result = db.prepare(
            "INSERT INTO users (email, password_hash) VALUES (?,?)"
        ).run(email,passwordHash);

        res.status(201).json({ id: result.lastInsertRowid });
    });

    app.post("/api/login", (req, res) => {
        const { email, password } = req.body;
        const user = db.prepare("SELECT * FROM users WHERE email = ?").get(email);
        const ok = user && verifyPassword(password, user.password_hash);
        if (!ok) return res.status(401).json({ error: "Invalid email or password "});

        res.json({ id:user.id });
    })

    app.get("/api/tasks", (req, res) => {
        res.json({ tasks: db.prepare("SELECT * FROM tasks").all()});
    });
    return {app, db}; 
}