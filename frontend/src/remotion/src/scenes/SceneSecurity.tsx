import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate } from "remotion";

export const SceneSecurity: React.FC = () => {
  const frame = useCurrentFrame();

  const iconOpacity = interpolate(
    frame,
    [0, 30],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const iconScale = interpolate(
    frame,
    [0, 30],
    [0.8, 1],
    { extrapolateRight: "clamp" }
  );

  const headlineOpacity = interpolate(
    frame,
    [15, 45],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const headlineTranslateY = interpolate(
    frame,
    [15, 45],
    [20, 0],
    { extrapolateRight: "clamp" }
  );

  const bodyOpacity = interpolate(
    frame,
    [30, 60],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  return (
    <AbsoluteFill
      style={{
        backgroundColor: "#0d0d0f",
        backgroundImage: "radial-gradient(circle at center, rgba(124, 109, 240, 0.05) 0%, #0d0d0f 70%)",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
      }}
    >
      {/* Shield icon */}
      <div
        style={{
          opacity: iconOpacity,
          transform: `scale(${iconScale})`,
          marginBottom: "32px",
        }}
      >
        <svg
          width="64"
          height="64"
          viewBox="0 0 24 24"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            d="M12 2L3 7V12C3 17.52 6.84 22.74 12 24C17.16 22.74 21 17.52 21 12V7L12 2Z"
            fill="#7c6df0"
            stroke="#7c6df0"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
          <path
            d="M9 12L11 14L15 10"
            stroke="#0d0d0f"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </div>

      {/* Headline */}
      <div
        style={{
          fontSize: "48px",
          fontWeight: 700,
          color: "#f0f0f0",
          opacity: headlineOpacity,
          transform: `translateY(${headlineTranslateY}px)`,
          marginBottom: "16px",
        }}
      >
        End-to-end encrypted.
      </div>

      {/* Body */}
      <div
        style={{
          fontSize: "16px",
          color: "#6b6b80",
          opacity: bodyOpacity,
          textAlign: "center",
        }}
      >
        AES-256-GCM. Your data never leaves your control.
      </div>
    </AbsoluteFill>
  );
};
