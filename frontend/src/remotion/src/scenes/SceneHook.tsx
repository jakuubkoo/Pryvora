import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate } from "remotion";
import { Label } from "../components/Label";

export const SceneHook: React.FC = () => {
  const frame = useCurrentFrame();

  const labelOpacity = interpolate(
    frame,
    [0, 20],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const line1Opacity = interpolate(
    frame,
    [0, 30],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const line1TranslateX = interpolate(
    frame,
    [0, 30],
    [-60, 0],
    { extrapolateRight: "clamp" }
  );

  const line2Opacity = interpolate(
    frame,
    [25, 55],
    [0, 1],
    { extrapolateRight: "clamp" }
  );

  const line2TranslateX = interpolate(
    frame,
    [25, 55],
    [-60, 0],
    { extrapolateRight: "clamp" }
  );

  const shapeScale = interpolate(
    frame,
    [0, 120],
    [0.96, 1],
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
          <Label>PRODUCTIVITY REIMAGINED</Label>
        </div>
        <div
          style={{
            fontSize: "80px",
            fontWeight: 700,
            color: "#f0f0f0",
            lineHeight: 1.1,
            opacity: line1Opacity,
            transform: `translateX(${line1TranslateX}px)`,
          }}
        >
          Your workspace.
        </div>
        <div
          style={{
            fontSize: "80px",
            fontWeight: 700,
            color: "#7c6df0",
            lineHeight: 1.1,
            marginTop: "8px",
            opacity: line2Opacity,
            transform: `translateX(${line2TranslateX}px)`,
          }}
        >
          Your rules.
        </div>
      </div>

      {/* Right side - Abstract shape */}
      <div
        style={{
          width: "50%",
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          position: "relative",
        }}
      >
        <div
          style={{
            width: "400px",
            height: "500px",
            backgroundColor: "#141417",
            borderRadius: "24px",
            opacity: 0.25,
            transform: `scale(${shapeScale})`,
            border: "1px solid #1f1f26",
          }}
        />
      </div>
    </AbsoluteFill>
  );
};
