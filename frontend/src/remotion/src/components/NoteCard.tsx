import React from "react";
import { useCurrentFrame, interpolate } from "remotion";

interface NoteCardProps {
  title: string;
  body: string;
  category: "STRATEGY" | "PERSONAL" | "IDEAS";
  timestamp: string;
  delay: number;
}

const categoryColors = {
  STRATEGY: "#7c6df0",
  PERSONAL: "#4ade80",
  IDEAS: "#a89ff5",
};

export const NoteCard: React.FC<NoteCardProps> = ({
  title,
  body,
  category,
  timestamp,
  delay,
}) => {
  const frame = useCurrentFrame();

  const opacity = interpolate(
    frame,
    [delay, delay + 20],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const scale = interpolate(
    frame,
    [delay, delay + 20],
    [0.92, 1],
    { extrapolateRight: "clamp" }
  );

  return (
    <div
      style={{
        backgroundColor: "#141417",
        border: "1px solid #1f1f26",
        borderRadius: "12px",
        padding: "16px",
        opacity,
        transform: `scale(${scale})`,
        display: "flex",
        flexDirection: "column",
        gap: "8px",
      }}
    >
      <span
        style={{
          color: categoryColors[category],
          fontSize: "10px",
          fontWeight: 700,
          textTransform: "uppercase",
          letterSpacing: "0.1em",
        }}
      >
        {category}
      </span>
      <div
        style={{
          fontSize: "14px",
          fontWeight: 600,
          color: "#f0f0f0",
        }}
      >
        {title}
      </div>
      <div
        style={{
          fontSize: "12px",
          color: "#6b6b80",
          lineHeight: 1.5,
        }}
      >
        {body}
      </div>
      <div
        style={{
          fontSize: "11px",
          color: "#4a4a5c",
          marginTop: "4px",
        }}
      >
        {timestamp}
      </div>
    </div>
  );
};
