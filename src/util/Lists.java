/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Classes/Class.java to edit this template
 */
package util;

import java.sql.ResultSetMetaData;
import java.sql.SQLException;
import java.util.ArrayList;

/**
 *
 * @author SistemasRC0011
 */
public class Lists {

    public static Object[][] getListaEmpleados() {
        AdaptadorMySql ad = new AdaptadorMySql();

        Object[][] datos = new Object[1][];

        try {

            String consulta = "SELECT cod_interno, concat(primer_apellido,', ', nombres) as empleado, mc.descripcion as modalidad "
                    + "from personal p "
                    + "join modalidad_contratacion mc on mc.id_modalidad = p.id_modalida_contratacion"
                    + " where activo = 'S'"
                    + ";";

            String bloque = "SELECT rpad(CEDULA_CUIL,11,' ') || rpad(PRIMER_APELLIDO || ' ' ||nombres ,30,' ') || rpad(TAREA_ASIGNADA,30, ' ') || rpad(calificacion,30, ' ')|| \n"
                    + "lpad(0,2,'0')|| \n"
                    + "\n"
                    + "lpad((SELECT COUNT(ID_PARENTESCO) FROM FAMILIARES f WHERE P.ID_PERSONAL = F.ID_PERSONAL AND F.ID_PARENTESCO = 4) ,2,'0') || \n"
                    + "\n"
                    + "lpad(p.ID_SITUACION_REVISTA,4,'0') || lpad(p.ID_CONDICION_LABORAL ,4,'0') || lpad(p.ID_ACTIVIDADES_LABORALES  ,4,'0') ||\n"
                    + "lpad(COALESCE(mc.CODIGO,0),4,'0') || lpad('1',4,'0') || lpad('16',4,'0') || \n"
                    + "'000001'||lpad(p.ID_SITUACION_REVISTA,4,'0')||'0100000000000030' ||\n"
                    + "lpad((SELECT sum(importe) FROM recibos r WHERE r.ID_LIQUIDACION = 183 AND id_personal = p.id_personal AND r.TIPOSIJP = 1 AND r.tipo = 1),9,' ')||\n"
                    + "lpad(COALESCE((SELECT sum(importe) FROM recibos r WHERE r.ID_LIQUIDACION = 183 AND id_personal = p.id_personal AND r.TIPOSIJP = 3 AND r.tipo = 1),0),9,' ')||\n"
                    + "lpad('0,00',9,' ') ||\n"
                    + "lpad(COALESCE((SELECT sum(importe) FROM recibos r WHERE r.ID_LIQUIDACION = 183 AND id_personal = p.id_personal AND r.tipo = 2),0),9,' ')||\n"
                    + "lpad('0,00',9,' ') ||\n"
                    + "lpad(COALESCE((SELECT sum(importe) FROM recibos r WHERE r.ID_LIQUIDACION = 183 AND id_personal = p.id_personal AND r.TIPOSIJP = 2 AND r.tipo = 1),0),9,' ')||\n"
                    + "lpad(COALESCE((SELECT sum(importe) FROM recibos r WHERE r.ID_LIQUIDACION = 183 AND id_personal = p.id_personal AND item = 500)*6,0),9,' ')||\n"
                    + "lpad('1',1,'0')||\n"
                    + "lpad('1',2,'0')||\n"
                    + "lpad(COALESCE((SELECT sum(importe) FROM recibos r WHERE r.ID_LIQUIDACION = 183 AND id_personal = p.id_personal AND item = 501),0),9,' ')||\n"
                    + "lpad(COALESCE((SELECT sum(importe) FROM recibos r WHERE r.ID_LIQUIDACION = 183 AND id_personal = p.id_personal AND item IN (544,545)),0),9,' ')\n"
                    + "FROM personal p\n"
                    + "JOIN MODALIDAD_CONTRATACION mc ON mc.ID_MODALIDAD = p.ID_MODALIDA_CONTRATACION \n"
                    + "WHERE activo = 'S'\n"
                    + "--AND p.ID_SITUACION_REVISTA = 4\n"
                    + "ORDER BY primer_apellido, nombres";

            System.out.println(consulta);

            ad.consultar(consulta);

            ArrayList<Object[]> registros = new ArrayList();

            while (ad.resultados.next()) {

                Object[] temp = new Object[]{
                    ad.resultados.getString(1),
                    ad.resultados.getString(2),
                    ad.resultados.getString(3),
                    ad.resultados.getString(4)};

                registros.add(temp);
            }

            System.out.println("");

            datos = new Object[registros.size()][];
        } catch (Exception e) {

        }

        return datos;
    }

    public static void main(String[] args) {

    }

}
