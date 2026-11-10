import { Bar, BarChart, CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { useTheme } from '../context/ThemeContext';

export const STATUS_COLORS = { EN_PROCESO: '#0ea5e9', OBSERVADO: '#f59e0b', FINALIZADO: '#2eb8a0', RECHAZADO: '#ef4444' };

function useAxis() {
    const { dark } = useTheme();
    return {
        grid: dark ? '#1e3350' : '#e2e8f0',
        tick: { fill: dark ? '#8ea2bb' : '#5d6e84', fontSize: 12 },
        tooltip: {
            contentStyle: { background: dark ? '#112238' : '#fff', border: `1px solid ${dark ? '#1e3350' : '#e2e8f0'}`, borderRadius: 12, fontSize: 13 },
            labelStyle: { color: dark ? '#e7eef7' : '#0f1e33', fontWeight: 700 },
        },
    };
}

export function TrendChart({ data, series }) {
    const a = useAxis();
    const { dark } = useTheme();
    const tone = (c) => (dark && c === '#0f2440' ? '#f2d68a' : c);
    return (
        <ResponsiveContainer width="100%" height={260}>
            <LineChart data={data} margin={{ top: 8, right: 12, left: -16, bottom: 0 }}>
                <CartesianGrid stroke={a.grid} vertical={false} />
                <XAxis dataKey="label" tick={a.tick} axisLine={false} tickLine={false} />
                <YAxis tick={a.tick} axisLine={false} tickLine={false} allowDecimals={false} />
                <Tooltip {...a.tooltip} />
                <Legend wrapperStyle={{ fontSize: 12 }} iconType="circle" />
                {series.map((s) => <Line key={s.key} dataKey={s.key} name={s.name} stroke={tone(s.color)} strokeWidth={2.5} dot={{ r: 3 }} type="monotone" />)}
            </LineChart>
        </ResponsiveContainer>
    );
}

export function StatusDonut({ counts, labels }) {
    const a = useAxis();
    const data = Object.entries(counts).map(([k, v]) => ({ key: k, name: labels[k] || k, value: v })).filter((d) => d.value > 0);
    return (
        <ResponsiveContainer width="100%" height={260}>
            <PieChart>
                <Pie data={data} dataKey="value" nameKey="name" innerRadius={62} outerRadius={95} paddingAngle={2} stroke="none">
                    {data.map((d) => <Cell key={d.key} fill={STATUS_COLORS[d.key]} />)}
                </Pie>
                <Tooltip {...a.tooltip} />
                <Legend wrapperStyle={{ fontSize: 12 }} iconType="circle" />
            </PieChart>
        </ResponsiveContainer>
    );
}

export function StackedBars({ data, height = 360 }) {
    const a = useAxis();
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} layout="vertical" margin={{ top: 0, right: 16, left: 8, bottom: 0 }}>
                <CartesianGrid stroke={a.grid} horizontal={false} />
                <XAxis type="number" tick={a.tick} axisLine={false} tickLine={false} allowDecimals={false} />
                <YAxis type="category" dataKey="code" tick={a.tick} axisLine={false} tickLine={false} width={70} />
                <Tooltip {...a.tooltip} labelFormatter={(l, p) => p?.[0]?.payload?.name || l} cursor={{ fill: 'rgba(46,184,160,0.06)' }} />
                <Legend wrapperStyle={{ fontSize: 12 }} iconType="circle" />
                <Bar dataKey="finalizado" name="Finalizado" stackId="a" fill={STATUS_COLORS.FINALIZADO} />
                <Bar dataKey="en_proceso" name="En proceso" stackId="a" fill={STATUS_COLORS.EN_PROCESO} />
                <Bar dataKey="observado" name="Observado" stackId="a" fill={STATUS_COLORS.OBSERVADO} />
                <Bar dataKey="rechazado" name="Rechazado" stackId="a" fill={STATUS_COLORS.RECHAZADO} radius={[0, 6, 6, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}

export function SimpleBars({ data, dataKey = 'total', nameKey = 'label', color = '#2eb8a0', height = 260 }) {
    const a = useAxis();
    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} margin={{ top: 8, right: 12, left: -16, bottom: 0 }}>
                <CartesianGrid stroke={a.grid} vertical={false} />
                <XAxis dataKey={nameKey} tick={a.tick} axisLine={false} tickLine={false} interval={0} />
                <YAxis tick={a.tick} axisLine={false} tickLine={false} allowDecimals={false} />
                <Tooltip {...a.tooltip} cursor={{ fill: 'rgba(46,184,160,0.06)' }} />
                <Bar dataKey={dataKey} fill={color} radius={[6, 6, 0, 0]} maxBarSize={44} />
            </BarChart>
        </ResponsiveContainer>
    );
}
