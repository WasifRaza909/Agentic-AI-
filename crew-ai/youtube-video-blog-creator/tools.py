import re

from crewai.tools import tool
from youtube_transcript_api import YouTubeTranscriptApi


def _extract_video_id(url_or_id: str) -> str:
    """Accept a full YouTube URL or a bare 11-char video ID and return the ID."""
    value = url_or_id.strip()

    # Already a bare video ID (e.g. "dQw4w9WgXcQ")
    if re.fullmatch(r"[0-9A-Za-z_-]{11}", value):
        return value

    patterns = [
        r"(?:v=|/watch\?v=)([0-9A-Za-z_-]{11})",
        r"(?:youtu\.be/|/embed/|/v/|/shorts/)([0-9A-Za-z_-]{11})",
    ]
    for pattern in patterns:
        match = re.search(pattern, value)
        if match:
            return match.group(1)

    raise ValueError(f"Could not extract a YouTube video ID from: {url_or_id!r}")


@tool("YouTube Video Transcript")
def yt_tool(video_url_or_id: str) -> str:
    """Fetch the transcript (subtitles) of a YouTube video.

    Pass a full YouTube URL (watch, youtu.be, shorts or embed link) or a bare
    11-character video ID. Returns the plain-text transcript so the agent can
    research and summarize what the video says.
    """
    video_id = _extract_video_id(video_url_or_id)
    api = YouTubeTranscriptApi()

    try:
        fetched = api.fetch(video_id, languages=["en", "en-US", "en-GB"])
    except Exception:
        # Fall back to whatever transcript exists (auto-generated / other language).
        try:
            transcript_list = api.list(video_id)
            transcript = next(iter(transcript_list))
            fetched = transcript.fetch()
        except Exception as exc:  # noqa: BLE001
            return f"Could not fetch a transcript for video {video_id}: {exc}"

    text = " ".join(snippet.text.strip() for snippet in fetched if snippet.text.strip())
    if not text:
        return f"No transcript text was found for video {video_id}."
    return text
