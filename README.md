# EmoNeighbour

A community-driven emotional feedback web application that helps community managers understand how residents feel and respond early, instead of waiting for complaints. Developed as my Degree Final Year Project (FYP).

## Problem

Messaging groups like WhatsApp and Facebook are where residents talk, but they give community managers no structured way to see emotional trends across a neighbourhood. EmoNeighbour fills that gap and shifts community management from **reactive** to **proactive**.

## Features

- **Resident feedback:** residents log in and submit emotional feedback, with their block assigned automatically from their account
- **Two-stage NLP analysis:**
  1. **Sentiment classification** with RoBERTa (`cardiffnlp/twitter-roberta-base-sentiment-latest`), outputting positive, neutral and negative probabilities, with **VADER** as a fallback
  2. **Domain classification** by keyword matching into Stress, Loneliness, Fear, Anger, Discontent, Support and Appreciation
- **Admin dashboard:** charts showing domain distribution and sentiment trends
- **Zone x Week heatmap:** a colour-coded matrix showing which zones are struggling in which weeks
- **Keyword Alerts:** system alerts the admin and community leaders if a specific keyword is detected e.g. suicide, harm, etc.
- **PDF heatmap download:** Community Leaders are able to download heatmaps in pdf form for better analysis.

## Tech Stack

| Part | Technology |
|------|------------|
| Web application | [PHP, HTML/CSS, JavaScript] |
| Database | [MySQL via XAMPP] |
| NLP | Python, Hugging Face Transformers, VADER |
| Sentiment model | `cardiffnlp/twitter-roberta-base-sentiment-latest` |
| Web-to-NLP communication | [e.g. Flask API / PHP calling a Python script] |

## My Role

I designed and developed the full system: [web application, database design, NLP pipeline, dashboard and heatmap] as a solo project.

## What I Learned

- Integrating a transformer-based NLP model into a web application
- Combining ML sentiment analysis with rule-based domain classification
- Designing dashboards that turn raw text into actionable community insights

## Future Improvements

- e.g. Replace keyword matching with a trained domain classifier
- e.g. Multilingual support for Malay and Chinese feedback
