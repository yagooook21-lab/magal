import os
from typing import List
from PIL import Image
import torch
from diffusers import StableDiffusionVideoPipeline, CogVideoXPipeline


def load_pipeline(model_name: str, device: str):
    """Load a text‑to‑video diffusion pipeline.
    Supports CogVideoX (if installed) and StableDiffusionVideoPipeline as fallback.
    """
    if model_name is None:
        # Choose default based on device capabilities
        if device == "cuda":
            model_name = "THUDM/cogvideo-x-xt"
        else:
            model_name = "stabilityai/stable-video-diffusion-img2vid-xt"
    try:
        if "cogvideo" in model_name.lower():
            pipe = CogVideoXPipeline.from_pretrained(model_name, torch_dtype=torch.float16 if device == "cuda" else torch.float32)
        else:
            pipe = StableDiffusionVideoPipeline.from_pretrained(model_name, torch_dtype=torch.float16 if device == "cuda" else torch.float32)
        pipe = pipe.to(device)
        return pipe
    except Exception as e:
        raise RuntimeError(f"Failed to load model {model_name}: {e}")


def generate_video_frames(prompt: str, model_name: str = None, device: str = "cpu", fps: int = 24, width: int = 720, height: int = 480) -> List[Image.Image]:
    """Generate video frames from a text prompt.

    Returns a list of PIL.Image objects.
    """
    pipe = load_pipeline(model_name, device)
    # The diffusion pipelines accept a ``num_frames`` or ``num_inference_steps`` parameter; we use a modest number for speed.
    num_frames = fps * 2  # default 2‑second clip
    # Generate video tensor (B, C, T, H, W)
    generator = torch.manual_seed(42)
    video = pipe(prompt, num_frames=num_frames, height=height, width=width, generator=generator).frames  # type: ignore
    # Convert tensor to list of PIL Images
    frames = []
    for i in range(video.shape[2]):
        frame = video[0, :, i, :, :].permute(1, 2, 0)  # H, W, C
        frame = (frame * 255).cpu().numpy().astype("uint8")
        frames.append(Image.fromarray(frame))
    return frames
