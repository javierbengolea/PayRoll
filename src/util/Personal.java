/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Classes/Class.java to edit this template
 */
package util;

import java.sql.Connection;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.ArrayList;
import static util.FirebirdConnUtils.getFirebirdConnection;

/**
 *
 * @author SistemasRC0011
 */
public class Personal {

    public static String[][] listadoPersonal(){

        String[][] salida = {{"nombre", "apellido"}};
        
        try{

        Connection con = getFirebirdConnection();

        Statement stmt = con.createStatement();

        String consulta = "select cod_interno as legajo, primer_apellido || ', ' || nombres as empleado, mc.descripcion as planta, "
                + "cedula_cuil as cuit "
                + "from personal p join modalidad_contratacion mc on mc.id_modalidad = p.id_modalida_contratacion "
                + "where activo = 'S' order by primer_apellido, nombres";

        ResultSet resultados = stmt.executeQuery(consulta);
        
        ArrayList<String []> registros = new ArrayList();
        
        while(resultados.next()){
            
            String legajo = resultados.getString(1);
            String empleado = resultados.getString(2);
            String planta = resultados.getString(3);
            String cuil = resultados.getString(4);
            
            String [] temp = new String[]{legajo, empleado, planta.replace("Planta ", ""), cuil};
            
            registros.add(temp);
        }
        
        salida = new String[registros.size()][];
        
        for (int i = 0; i < salida.length; i++) {
            salida[i] = registros.get(i);            
        }
        
        }catch(Exception e){
            System.err.println(e);
        }
        return salida;
    }

    public static void main(String [] args) throws Exception{
        
        String [][] listado = listadoPersonal();
        
        for (int i = 0; i < listado.length; i++) {
            for (int j = 0; j < listado[0].length; j++) {
                System.out.print(listado[i][j]);
                
            }
            System.out.println("");
        }
        
    }

}
