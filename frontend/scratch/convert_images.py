import os
from PIL import Image

assets_dir = r"c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\public\assets"

for filename in os.listdir(assets_dir):
    if filename.endswith(".png"):
        png_path = os.path.join(assets_dir, filename)
        webp_path = os.path.splitext(png_path)[0] + ".webp"
        
        print(f"Converting {filename} to WebP...")
        try:
            with Image.open(png_path) as img:
                img.save(webp_path, "WEBP", quality=85)
            print(f"Successfully saved {os.path.basename(webp_path)}")
        except Exception as e:
            print(f"Failed to convert {filename}: {e}")
