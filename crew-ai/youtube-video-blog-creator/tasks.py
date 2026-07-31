from crewai import Task
from tools import yt_tool
from agents import blog_researcher, blog_writer

## Research Task
research_task = Task(
    description=(
        "Research the YouTube video about {topic} located at {video_url}. "
        "Use the YouTube Video Transcript tool with that URL to pull the "
        "transcript, then extract the key points, technologies and takeaways."
    ),
    expected_output="A comprehensive 3 paragraphs long report based on the {topic} of video content",
    tools=[yt_tool],
    agent=blog_researcher,
)

## Writing Task
write_task = Task(
    description=(
        "Using the transcript of the video at {video_url} about {topic}, "
        "create engaging blog content that summarizes and explains it."
    ),
    expected_output="Summarize the infoc from the youtube channel video on the topic{topic}",
    agent=blog_writer,
    tools=[yt_tool],
    async_execution=False, # If true all agents will be simultaneously working on the task, if false the agents will work in sequence
    output_file="new-blog-post.md" # Example of output customization
)