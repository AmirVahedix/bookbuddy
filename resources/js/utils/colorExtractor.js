/**
 * Utility to dynamically extract dominant color theme from book cover images.
 */

const themeCache = new Map();

/**
 * Convert RGB to HSL
 */
function rgbToHsl(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;

    if (max === min) {
        h = s = 0; // achromatic
    } else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r: h = (g - b) / d + (g < b ? 6 : 0); break;
            case g: h = (b - r) / d + 2; break;
            case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
    }

    return [Math.round(h * 360), Math.round(s * 100), Math.round(l * 100)];
}

/**
 * Extract dominant cover color and compute dynamic theme styles.
 * Returns a Promise resolving to theme style objects or null if extraction fails.
 */
export function extractCoverTheme(imageUrl) {
    if (!imageUrl) {
        return Promise.resolve(null);
    }

    if (themeCache.has(imageUrl)) {
        return Promise.resolve(themeCache.get(imageUrl));
    }

    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'Anonymous';

        img.onload = () => {
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    resolve(null);
                    return;
                }

                const width = 50;
                const height = 50;
                canvas.width = width;
                canvas.height = height;

                ctx.drawImage(img, 0, 0, width, height);
                const imgData = ctx.getImageData(0, 0, width, height).data;

                let rSum = 0, gSum = 0, bSum = 0, count = 0;
                let vibrantR = 0, vibrantG = 0, vibrantB = 0;
                let maxSat = -1;

                for (let i = 0; i < imgData.length; i += 4) {
                    const r = imgData[i];
                    const g = imgData[i + 1];
                    const b = imgData[i + 2];
                    const a = imgData[i + 3];

                    if (a < 128) continue; // Skip semi-transparent pixels

                    // Calculate saturation
                    const [h, s, l] = rgbToHsl(r, g, b);

                    // Ignore extreme black / extreme white for saturation search
                    if (l > 10 && l < 92) {
                        if (s > maxSat) {
                            maxSat = s;
                            vibrantR = r;
                            vibrantG = g;
                            vibrantB = b;
                        }
                    }

                    rSum += r;
                    gSum += g;
                    bSum += b;
                    count++;
                }

                if (count === 0) {
                    resolve(null);
                    return;
                }

                // Use vibrant color if good saturation found, else average color
                const finalR = maxSat > 15 ? vibrantR : Math.round(rSum / count);
                const finalG = maxSat > 15 ? vibrantG : Math.round(gSum / count);
                const finalB = maxSat > 15 ? vibrantB : Math.round(bSum / count);

                const [h, s] = rgbToHsl(finalR, finalG, finalB);

                // Build HSL colors for dark glass gradient and vibrant accent elements
                const darkSat = Math.max(25, Math.min(s, 65));
                const glassViaColor = `hsla(${h}, ${darkSat}%, 15%, 0.90)`;
                const glassFromColor = `hsl(${h}, ${Math.max(15, darkSat - 10)}%, 7%)`;
                
                const accentHue = h;
                const accentSat = Math.max(65, s);
                const textAccentStyle = { color: `hsl(${accentHue}, ${accentSat}%, 75%)` };
                const progressGradStyle = {
                    background: `linear-gradient(to right, hsl(${accentHue}, ${accentSat}%, 55%), hsl(${(accentHue + 25) % 360}, ${accentSat}%, 45%))`
                };
                const overlayBgStyle = {
                    background: `linear-gradient(to top, ${glassFromColor} 0%, ${glassViaColor} 55%, transparent 100%)`
                };
                const bgGradientStyle = {
                    background: `linear-gradient(to bottom right, hsl(${h}, ${accentSat}%, 25%), hsl(${(h + 30) % 360}, ${accentSat}%, 12%), hsl(222, 47%, 11%))`
                };

                const themeResult = {
                    overlayBgStyle,
                    textAccentStyle,
                    progressGradStyle,
                    bgGradientStyle,
                };

                themeCache.set(imageUrl, themeResult);
                resolve(themeResult);
            } catch (e) {
                // Return null on canvas error (e.g. CORS)
                resolve(null);
            }
        };

        img.onerror = () => {
            resolve(null);
        };

        img.src = imageUrl;
    });
}
