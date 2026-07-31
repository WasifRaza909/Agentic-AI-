from crewai import Agent, LLM
from tools import yt_tool

from dotenv import load_dotenv
load_dotenv()

import os
os.environ["GEMINI_API_KEY"] = os.getenv("GEMINI_API_KEY")

llm = LLM(
    model=os.getenv("MODEL_NAME", "gemini/gemini-2.5-flash"),
    api_key=os.getenv("GEMINI_API_KEY"),
)

## Create a senior blog content researcher

blog_researcher = Agent(
    role="Blog Researcher from Youtube Videos",
    goal="get the relevant video content for the topic{topic} from Yt channel",
    verbose=True,
    memory=True,
    backstory=(
        "Expert in understanding videos in AI Data Science, MachineLearning And Gen AI and providing suggestions."
    ),
    llm=llm,
    tools=[yt_tool],
    allow_delegation=True,
)


## Creating a senior blog writing agent with YT tool

blog_writer = Agent(
    role="Blog Writer",
    goal="Narrate compelling tech stories about the video {topic} from Yt channel",
    verbose=True,
    memory=True,
    backstory=(
        "With a flair for simplifying complex topics, you craft"
        "engaging narrratives that captivate and educate, bringing new"
        "discoveries to light in an accessible manner"
    ),
    tools=[yt_tool],
    llm=llm,
    allow_delegation=False,
)
