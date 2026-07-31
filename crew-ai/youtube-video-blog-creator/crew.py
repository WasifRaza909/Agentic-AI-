from crewai import Crew, Process
from agents import blog_researcher, blog_writer
from tasks import research_task, write_task

crew = Crew(
    agents=[blog_researcher, blog_writer],
    tasks=[research_task, write_task],
    process=Process.sequential,
    memory=False,  # crew memory uses OpenAI embeddings by default; off since we're on Gemini
    cache=True,
    max_rpm=2,
    share_crew=True
)

## Start the task execution process
result = crew.kickoff(
    inputs={
        "topic": "Claude Code Crash Course For Developers",
        # A specific YouTube video URL (or bare 11-char video ID) to research.
        "video_url": "https://www.youtube.com/watch?v=C2GpeepcmYs",
    }
)