# src/latest_ai_flow/crews/content_crew/content_crew.py
from pathlib import Path

from crewai.project import load_crew


def kickoff_content_crew(inputs: dict):
  crew, default_inputs = load_crew(Path(__file__).with_name("crew.jsonc"))
  return crew.kickoff(inputs={**default_inputs, **inputs})