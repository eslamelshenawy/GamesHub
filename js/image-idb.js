// IndexedDB image cache for add-account and index pages
// Provides: saveImageToIDB, getImageFromIDB, clearImagesFromIDB
// Usage: same as sessionStorage, but async

const DB_NAME = 'imageCacheDB';
const DB_VERSION = 1;
const STORE_NAME = 'images';

function openIDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = function(e) {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME);
            }
        };
        req.onsuccess = function(e) { resolve(e.target.result); };
        req.onerror = function(e) { reject(e.target.error); };
    });
}

async function saveImageToIDB(key, data) {
    const db = await openIDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).put(data, key);
        tx.oncomplete = () => resolve();
        tx.onerror = e => reject(e.target.error);
    });
}

async function getImageFromIDB(key) {
    const db = await openIDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const req = tx.objectStore(STORE_NAME).get(key);
        req.onsuccess = () => resolve(req.result);
        req.onerror = e => reject(e.target.error);
    });
}

async function clearImagesFromIDB() {
    const db = await openIDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).clear();
        tx.oncomplete = () => resolve();
        tx.onerror = e => reject(e.target.error);
    });
}

window.saveImageToIDB = saveImageToIDB;
window.getImageFromIDB = getImageFromIDB;
window.clearImagesFromIDB = clearImagesFromIDB;
