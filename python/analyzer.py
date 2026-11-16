# EmoNeighbour - AI Sentiment Analyzer 

from flask import Flask, request, jsonify
from transformers import AutoTokenizer, AutoModelForSequenceClassification
from vaderSentiment.vaderSentiment import SentimentIntensityAnalyzer
import torch

app = Flask(__name__)

# LOAD MODELS

print("Loading RoBERTa...")
roberta_tokenizer = AutoTokenizer.from_pretrained("cardiffnlp/twitter-roberta-base-sentiment-latest")
roberta_model = AutoModelForSequenceClassification.from_pretrained("cardiffnlp/twitter-roberta-base-sentiment-latest")

print("Loading VADER...")
vader = SentimentIntensityAnalyzer()

device = torch.device("cpu")


#  DOMAIN CLASSIFICATION 

domain_keywords = {
    # ===== STRESS / PRESSURE =====
    "stress": [
        "stress", "stressed", "overwhelmed", "pressure", "pressured", "burnout",
        "burned out", "exhausted", "tired", "fatigued", "drained", "too much",
        "cant cope", "cannot cope", "coping", "breaking down",
        "panic", "panicking", "panic attack",
        "anxious", "anxiety", "on edge", "tense",
        "workload", "deadlines", "too busy", "overworked",
        "mental load", "heavy", "mentally tired"
    ],

    # ===== FEAR / UNSAFETY =====
    "fear": [
        "fear", "afraid", "scared", "terrified", "panic",
        "unsafe", "not safe", "danger", "threat", "threatened",
        "worried", "worry", "anxious", "anxiety",
        "paranoid", "nervous", "on edge",
        "frightened", "shaken",
        "noise", "noises", "screaming", "shouting",
        "break in", "robbed", "robbery", "stalker", "followed",
        "harassed", "harassment"
    ],

    # ===== LONELINESS / ISOLATION =====
    "loneliness": [
        "lonely", "alone", "isolated", "abandoned", "left out",
        "no one cares", "nobody cares",
        "ignored", "neglected", "unnoticed",
        "empty", "emptiness", "hollow",
        "disconnected", "cut off",
        "unloved", "unwanted",
        "no friends", "no support",
        "by myself", "on my own"
    ],

    # ===== ANGER / FRUSTRATION =====
    "anger": [
        "angry", "anger", "furious", "rage", "mad",
        "pissed", "pissed off", "fed up",
        "frustrated", "irritated", "annoyed",
        "hate", "hatred", "resent",
        "sick of", "done with",
        "shouting", "yelling", "arguing",
        "disgusted", "outraged",
        "unfair", "injustice"
    ],

    # ===== SADNESS / DEPRESSION =====
    "sadness": [
        "sad", "sadness", "depressed", "depression",
        "low", "very low", "down", "downcast",
        "hopeless", "helpless",
        "cry", "crying", "tears",
        "miserable", "heartbroken",
        "empty", "numb",
        "worthless", "useless",
        "heavy heart", "broken",
        "grief", "grieving", "loss"
    ],

    # ===== SUPPORT / HELP SEEKING =====
    "support": [
        "help", "need help", "please help",
        "support", "supported", "supportive",
        "assistance", "assist", "aid",
        "someone help", "anyone help",
        "reach out", "talk to someone",
        "need someone", "need advice",
        "guidance", "counseling", "therapy",
        "mental health support"
    ],

    # ===== APPRECIATION / POSITIVE =====
    "appreciation": [
        "thank you", "thanks", "thankful", "grateful",
        "appreciate", "appreciated",
        "happy", "happiness", "glad",
        "relieved", "relief",
        "blessed", "content",
        "good", "great", "positive",
        "improved", "better now",
        "satisfied", "peaceful"
    ],

    # ===== DISCONTENT / COMPLAINT =====
    "discontent": [
        "disappointed", "disappointment",
        "unsatisfied", "not satisfied",
        "complain", "complaint",
        "bad", "poor", "terrible",
        "unacceptable", "awful",
        "issue", "problem", "problems",
        "service is bad", "bad service",
        "not working", "broken",
        "failed", "failure",
        "neglected", "ignored"
    ],

    # ===== SELF-HARM / CRISIS (DOMAIN OVERRIDE) =====
    "self_harm": [
        "suicide", "suicidal", "kill myself", "end my life",
        "i want to die", "i wanna die", "die",
        "self harm", "self-harm", "cut myself",
        "hurt myself", "bleeding",
        "life is pointless", "no point living",
        "better off dead", "better without me",
        "i am a burden", "burden",
        "i give up", "i cant go on",
        "i want it to end",
        "disappear forever", "disappearing",
        "jump off", "hang myself",

        # Local / informal
        "nak mati", "bunuh diri", "mahu mati",
        "tak tahan", "tak boleh lagi"
    ]
}

def classify_domain(text):
    low = text.lower()
    for domain, words in domain_keywords.items():
        for w in words:
            if w in low:
                return domain
    return "neutral"


# EXTENDED HIGH-RISK DETECTION

danger_keywords = [

    # ===== SUICIDE / SELF HARM =====
    "suicide", "suicidal", "kill myself", "end my life", "take my life",
    "self-harm", "self harm", "cut myself", "hurt myself",
    "i want to die", "i wanna die", "wish i was dead",
    "life is not worth it", "life not worth living",
    "i can't go on", "can't go on", "i give up", "no point living",
    "die", "dying", "end everything", "disappear forever",
    "jump off", "jumping off", "hang myself",

    # ** MALAY / LOCAL SLANG **
    "nak mati", "rasa nak mati", "bunuh diri", "mahu mati",
    "tak boleh lagi", "habis lah", "tak tahan dah",

    # ===== EXTREME SADNESS / HOPELESSNESS =====
    "i'm done", "i am done", "nothing matters", "feel empty",
    "numb", "broken", "can't continue", "lost hope",
    "no future", "i hate my life", "hate my life",
    "feel useless", "i'm useless", "i am useless",
    "i can't handle this", "too much for me",

    # ===== VIOLENCE / HARM OTHERS =====
    "kill someone", "hurt someone", "stab someone",
    "beat someone", "attack someone",

    # ===== CRISIS LANGUAGE =====
    "crisis", "mental breakdown", "losing it", "snap",
    "i'm losing control", "can't control myself",

    # ===== SOFT PHRASES DETECTED IN SUICIDE STUDIES =====
    "better without me", "everyone better without me",
    "i don't matter", "nobody cares", "i am a burden",
    "burden to everyone", "my existence is a burden",
]

def check_high_risk(text):
    t = text.lower()
    for w in danger_keywords:
        if w in t:
            return 1, f"High-risk keyword detected: {w}"
    return 0, ""


# 4) SENTIMENT ANALYSIS

def analyze_sentiment_roberta(text):
    try:
        encoded = roberta_tokenizer(text, return_tensors="pt")
        outputs = roberta_model(**encoded)
        scores = outputs.logits.softmax(dim=1).detach().cpu().flatten()

        neg, neu, pos = scores[0], scores[1], scores[2]

        if pos == max(neg, neu, pos):
            return "positive", float(pos)
        elif neu == max(neg, neu, pos):
            return "neutral", float(neu)
        else:
            return "negative", float(neg)

    except:
        vs = vader.polarity_scores(text)
        if vs["compound"] >= 0.05:
            return "positive", vs["compound"]
        elif vs["compound"] <= -0.05:
            return "negative", vs["compound"]
        else:
            return "neutral", vs["compound"]


# 5) API ENDPOINT

@app.route("/analyze", methods=["POST"])
def analyze():
    data = request.get_json()
    text = data.get("text", "").strip()

    if text == "":
        return jsonify({
            "sentiment": "neutral",
            "score": 0.0,
            "model_used": "none",
            "domain": "neutral",
            "is_flagged": 0,
            "flag_reason": ""
        })

    sentiment, score = analyze_sentiment_roberta(text)
    domain = classify_domain(text)
    is_flagged, flag_reason = check_high_risk(text)

    return jsonify({
        "sentiment": sentiment,
        "score": float(score),
        "model_used": "roberta",
        "domain": domain,
        "is_flagged": is_flagged,
        "flag_reason": flag_reason
    })


# RUN SERVER

if __name__ == "__main__":
    print("AI Analyzer running at http://127.0.0.1:5000/analyze")
    app.run(host="127.0.0.1", port=5000)
