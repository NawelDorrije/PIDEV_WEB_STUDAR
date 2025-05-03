import sys
import cv2
from deepface import DeepFace

def detect_emotion(image_path):
    try:
        img = cv2.imread(image_path)
        if img is None:
            raise ValueError("Could not load image: possibly corrupted or invalid format")

        result = DeepFace.analyze(img, actions=['emotion'], enforce_detection=False)
        if not result or 'dominant_emotion' not in result[0]:
            return "unknown"
        return result[0]['dominant_emotion']
    except Exception as e:
        print(f"Error in detect_emotion: {e}", file=sys.stderr)
        return "error"

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python emotion_detection.py <image_path>", file=sys.stderr)
        sys.exit(1)

    image_path = sys.argv[1]
    emotion = detect_emotion(image_path)
    print(emotion)