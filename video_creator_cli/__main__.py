#!/usr/bin/env python3
"""video_creator_cli entry point

Usage:
  python -m video_creator_cli \
    --prompt "A blue sphere rotating" \
    --output output.mp4 \
    [--model MODEL_NAME] [--gpu] [--images-dir DIR] [--fps N] [--resolution WIDTHxHEIGHT]

The CLI loads the selected text‑to‑video model (default: CogVideoX if a CUDA GPU is detected, otherwise a lightweight CPU model), generates frames, optionally concatenates images from a folder, and writes an MP4 using MoviePy.
"""

import argparse
from pathlib import Path

from .generator import generate_video_frames
from .utils import frames_to_mp4, concatenate_image_folder


def parse_resolution(res_str: str):
    try:
        w, h = map(int, res_str.lower().split('x'))
        return w, h
    except Exception:
        raise argparse.ArgumentTypeError("Resolution must be in WIDTHxHEIGHT format, e.g., 1280x720")


def main():
    parser = argparse.ArgumentParser(description="Generate a video from a text prompt using an open‑source model.")
    parser.add_argument("--prompt", required=True, help="Text prompt describing the video.")
    parser.add_argument("--output", required=True, help="Path to the output MP4 file.")
    parser.add_argument("--model", default=None, help="Model identifier (e.g., THUDM/cogvideo-x-xt). If omitted the script chooses a suitable default.")
    parser.add_argument("--gpu", action="store_true", help="Force GPU usage (CUDA). If not set, the script selects GPU if available.")
    parser.add_argument("--images-dir", default=None, help="Optional folder with images to prepend/append to the generated video.")
    parser.add_argument("--fps", type=int, default=24, help="Frames per second for the output video.")
    parser.add_argument("--resolution", type=parse_resolution, default=(720, 480), help="Resolution WIDTHxHEIGHT, e.g., 1280x720.")
    args = parser.parse_args()

    frames = generate_video_frames(
        prompt=args.prompt,
        model_name=args.model,
        device="cuda" if args.gpu else "cpu",
        fps=args.fps,
        width=args.resolution[0],
        height=args.resolution[1],
    )

    if args.images_dir:
        extra_frames = concatenate_image_folder(args.images_dir, fps=args.fps, width=args.resolution[0], height=args.resolution[1])
        frames = extra_frames + frames

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    frames_to_mp4(frames, str(output_path), fps=args.fps)
    print(f"Video saved to {output_path}")

if __name__ == "__main__":
    main()
