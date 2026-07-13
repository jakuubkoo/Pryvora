import React from "react";
import { useCurrentFrame, interpolate } from "remotion";

interface StatBoxProps {
  label: string;
  value: number;
  delay?: number;
}

export const StatBox: React.FC<StatBoxProps> = ({ label, value, delay = 0 }) => {
  const frame = useCurrentFrame();
  
  const animatedValue = interpolate(
    frame,
    [60 + delay, 120 + delay],
    [0, value],
    { extrapolateRight: "clamp" }
  );

  const opacity = interpolate(
    frame,
    [0 + delay, 30 + delay],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const translateY = interpolate(
    frame,
    [0 + delay, 30 + delay],
    [20, 0],
    { extrapolateRight: "clamp" }
  );

  return (
    <div
      style={{
        backgroundColor: "#141417",
        border: "1px solid #1f1f26",
        borderRadius: "12px",
        padding: "20px",
        minWidth: "180px",
        opacity,
        transform: `translateY(${translateY}px)`,
      }}
    >
      <div
        style={{
          fontSize: "11px",
          fontWeight: 500,
          color: "#6b6b80",
          letterSpacing: "0.15em",
          textTransform: "uppercase",
          marginBottom: "8px",
        }}
      >
        {label}
      </div>
      <div
        style={{
          fontSize: "36px",
          fontWeight: 700,
          color: "#f0f0f0",
          fontVariantNumeric: "tabular-nums",
        }}
      >
        {Math.round(animatedValue)}
      </div>
    </div>
  );
};
