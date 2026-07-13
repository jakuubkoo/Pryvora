import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Sequence } from "remotion";
import { Label } from "../components/Label";
import { NoteCard } from "../components/NoteCard";

export const SceneNotes: React.FC = () => {
  const frame = useCurrentFrame();

  const labelOpacity = interpolate(
    frame,
    [0, 20],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const headlineOpacity = interpolate(
    frame,
    [10, 40],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const headlineTranslateY = interpolate(
    frame,
    [10, 40],
    [20, 0],
    { extrapolateRight: "clamp" }
  );

  const bodyOpacity = interpolate(
    frame,
    [20, 50],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const cardOpacity = interpolate(
    frame,
    [20, 70],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const cardTranslateX = interpolate(
    frame,
    [20, 70],
    [80, 0],
    { extrapolateRight: "clamp" }
  );

  return (
    <AbsoluteFill
      style={{
        backgroundColor: "#0d0d0f",
        display: "flex",
      }}
    >
      {/* Left side - Text */}
      <div
        style={{
          width: "50%",
          padding: "80px",
          display: "flex",
          flexDirection: "column",
          justifyContent: "center",
        }}
      >
        <div style={{ opacity: labelOpacity }}>
          <Label>KNOWLEDGE BASE</Label>
        </div>
        <div
          style={{
            fontSize: "56px",
            fontWeight: 700,
            color: "#f0f0f0",
            lineHeight: 1.1,
            opacity: headlineOpacity,
            transform: `translateY(${headlineTranslateY}px)`,
            marginBottom: "16px",
          }}
        >
          Your thoughts, organized.
        </div>
        <div
          style={{
            fontSize: "18px",
            color: "#6b6b80",
            lineHeight: 1.6,
            opacity: bodyOpacity,
          }}
        >
          A private vault for every thought that matters.
        </div>
      </div>

      {/* Right side - Note cards grid */}
      <div
        style={{
          width: "50%",
          padding: "60px 40px",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          opacity: cardOpacity,
          transform: `translateX(${cardTranslateX}px)`,
        }}
      >
        <div
          style={{
            display: "grid",
            gridTemplateColumns: "repeat(2, 1fr)",
            gap: "16px",
            width: "100%",
          }}
        >
          <Sequence from={10}>
            <NoteCard
              title="Q1 Strategy Review"
              body="Key objectives for the upcoming quarter..."
              category="STRATEGY"
              timestamp="2 hours ago"
              delay={0}
            />
          </Sequence>
          <Sequence from={25}>
            <NoteCard
              title="Weekend ideas"
              body="Hiking trail recommendations and gear list..."
              category="PERSONAL"
              timestamp="Yesterday"
              delay={0}
            />
          </Sequence>
          <Sequence from={40}>
            <NoteCard
              title="App feature concepts"
              body="Dark mode enhancements, keyboard shortcuts..."
              category="IDEAS"
              timestamp="2 days ago"
              delay={0}
            />
          </Sequence>
          <Sequence from={55}>
            <NoteCard
              title="Meeting notes"
              body="Action items from the product sync..."
              category="STRATEGY"
              timestamp="3 days ago"
              delay={0}
            />
          </Sequence>
        </div>
      </div>
    </AbsoluteFill>
  );
};
